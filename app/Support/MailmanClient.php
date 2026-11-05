<?php

namespace App\Support;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thin wrapper around Mailman 3 Core's own REST API (see config/services.php's
 * "mailman.api_url", e.g. http://host:8001/3.1). When "mailman.web_url" is set,
 * writes go through Postorius' mass subscribe/removal forms instead, logged in
 * as a list owner - for hosts that only expose the REST API read-only (same
 * approach as sGIS' Mailman3 class).
 */
class MailmanClient
{
    private readonly CookieJar $postoriusCookies;

    private bool $postoriusLoggedIn = false;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiUser,
        private readonly string $apiKey,
        private readonly bool|string $apiVerifyTls = true,
        private readonly ?string $webUrl = null,
        private readonly ?string $webUser = null,
        private readonly ?string $webPassword = null,
        private readonly bool|string $webVerifyTls = true,
    ) {
        $this->postoriusCookies = new CookieJar;
    }

    /**
     * @return array<int, array{email: string, self_link: string}>
     */
    public function listMembers(string $listId): array
    {
        $entries = $this->request()
            ->get("lists/{$listId}/roster/member")
            ->throw()
            ->json('entries', []);

        return collect($entries)
            ->map(fn (array $entry): array => [
                'email' => $entry['email'],
                'self_link' => $entry['self_link'],
            ])
            ->all();
    }

    /**
     * pre_verified/pre_confirmed/pre_approved all bypass Mailman's normal
     * opt-in confirmation mail - appropriate here because the DB-driven role
     * membership this syncs from is already the authoritative source of
     * truth for who belongs where (same principle as ldap:sync-roles/-groups
     * writing LDAP's uniqueMember directly, with no separate approval step).
     *
     * @param  array<int, string>  $emails
     * @return array<string, string> failed address => reason
     */
    public function subscribe(string $listId, array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        if ($this->usesPostorius()) {
            return $this->viaPostorius($listId, 'mass_subscribe', $emails, true, [
                'pre_verified' => 'on',
                'pre_confirmed' => 'on',
                'pre_approved' => 'on',
            ]);
        }

        $failed = [];
        foreach ($emails as $email) {
            try {
                $this->request()
                    ->asForm()
                    ->post('members', [
                        'list_id' => $listId,
                        'subscriber' => $email,
                        'role' => 'member',
                        'pre_verified' => 'true',
                        'pre_confirmed' => 'true',
                        'pre_approved' => 'true',
                    ])
                    ->throw();
            } catch (Throwable $e) {
                $failed[$email] = $e->getMessage();
            }
        }

        return $failed;
    }

    /**
     * $members are entries from listMembers(); over REST each one's
     * self_link is deleted directly, so Mailman's member-id URL scheme
     * never has to be known here.
     *
     * @param  array<int, array{email: string, self_link: string}>  $members
     * @return array<string, string> failed address => reason
     */
    public function unsubscribe(string $listId, array $members): array
    {
        if ($members === []) {
            return [];
        }

        if ($this->usesPostorius()) {
            return $this->viaPostorius($listId, 'mass_removal', array_column($members, 'email'), false);
        }

        $failed = [];
        foreach ($members as $member) {
            try {
                $this->request()->delete($member['self_link'])->throw();
            } catch (Throwable $e) {
                $failed[$member['email']] = $e->getMessage();
            }
        }

        return $failed;
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->apiUser, $this->apiKey)
            ->baseUrl(rtrim($this->baseUrl, '/').'/')
            ->withOptions(['verify' => $this->apiVerifyTls])
            ->timeout(10);
    }

    private function usesPostorius(): bool
    {
        return (bool) $this->webUrl;
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function submitPostoriusForm(string $path, array $fields): void
    {
        $this->loginToPostorius();

        $url = $this->postoriusUrl($path);
        $response = $this->postoriusRequest()->get($url);

        if ($response->forbidden()) {
            throw new RuntimeException("{$this->webUser} is not an owner of this list in Postorius");
        }

        $form = $response->throw()->body();

        // A lost session redirects to the login page instead.
        if (! str_contains($form, 'name="emails"')) {
            throw new RuntimeException("Postorius did not show the form at {$url} - session lost?");
        }

        $this->postoriusRequest()
            ->withHeaders(['Referer' => $url])
            ->asForm()
            ->post($url, ['csrfmiddlewaretoken' => $this->csrfToken($form)] + $fields)
            ->throw();
    }

    private function loginToPostorius(): void
    {
        if ($this->postoriusLoggedIn) {
            return;
        }

        $url = $this->postoriusUrl('accounts/login/');
        $form = $this->postoriusRequest()->get($url)->throw()->body();

        $response = $this->postoriusRequest()
            ->withHeaders(['Referer' => $url])
            ->asForm()
            ->post($url, [
                'csrfmiddlewaretoken' => $this->csrfToken($form),
                'login' => (string) $this->webUser,
                'password' => (string) $this->webPassword,
            ])
            ->throw();

        // Bad credentials re-render the login form with a 200.
        if (str_contains($response->body(), 'name="password"')) {
            throw new RuntimeException("Postorius login failed for {$this->webUser}");
        }

        $this->postoriusLoggedIn = true;
    }

    private function postoriusRequest(): PendingRequest
    {
        // Generous: a mass form subscribes every address before it responds.
        return Http::withOptions(['verify' => $this->webVerifyTls, 'cookies' => $this->postoriusCookies])
            ->timeout(60);
    }

    private function postoriusUrl(string $path): string
    {
        return rtrim((string) $this->webUrl, '/').'/'.$path;
    }

    private function csrfToken(string $html): string
    {
        if (! preg_match('/name="csrfmiddlewaretoken" value="([^"]+)"/', $html, $matches)) {
            throw new RuntimeException('No CSRF token in Postorius response');
        }

        return $matches[1];
    }

    /**
     * One mass form submission for all $emails. Postorius reports
     * per-address problems only as (localized) flash messages, so the
     * roster afterwards is what decides which addresses failed.
     *
     * @param  array<int, string>  $emails
     * @param  array<string, string>  $fields
     * @return array<string, string> failed address => reason
     */
    private function viaPostorius(string $listId, string $form, array $emails, bool $subscribed, array $fields = []): array
    {
        try {
            $this->submitPostoriusForm("postorius/lists/{$listId}/{$form}/", ['emails' => implode("\n", $emails)] + $fields);
            $roster = array_map(strtolower(...), array_column($this->listMembers($listId), 'email'));
        } catch (Throwable $e) {
            return array_fill_keys($emails, $e->getMessage());
        }

        $failed = [];
        foreach ($emails as $email) {
            if (in_array(strtolower($email), $roster, true) !== $subscribed) {
                $failed[$email] = $subscribed
                    ? 'still not subscribed after the Postorius request'
                    : 'still subscribed after the Postorius request';
            }
        }

        return $failed;
    }
}
