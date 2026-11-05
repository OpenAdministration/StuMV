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
    private const int POSTORIUS_CHUNK_SIZE = 25;

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
     * @return array<int, array{email: string, self_link: string, as_user: bool}>
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
                // Subscribed via a Mailman user account rather than the address itself.
                'as_user' => ($entry['subscription_mode'] ?? null) === 'as_user',
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
     * @param  array<int, array{email: string, self_link: string, as_user: bool}>  $members
     * @return array<string, string> failed address => reason
     */
    public function unsubscribe(string $listId, array $members): array
    {
        if ($members === []) {
            return [];
        }

        if ($this->usesPostorius()) {
            $failed = $this->viaPostorius($listId, 'mass_removal', array_column($members, 'email'), false);

            foreach ($members as $member) {
                if (($member['as_user'] ?? false) && isset($failed[$member['email']])) {
                    $failed[$member['email']] .= '; the membership belongs to a Mailman user account, which some Mailman versions cannot remove by address';
                }
            }

            return $failed;
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
     * @return array<int, string> Postorius' non-success messages from the response
     */
    private function submitPostoriusForm(string $path, array $fields): array
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

        $html = $this->postoriusRequest()
            ->withHeaders(['Referer' => $url])
            ->asForm()
            ->post($url, ['csrfmiddlewaretoken' => $this->csrfToken($form)] + $fields)
            ->throw()
            ->body();

        return $this->postoriusNotices($html);
    }

    /**
     * Django flash messages other than successes, e.g. "HTTP Error 400:
     * Membership is banned" - Postorius reports failures this way with a 200.
     *
     * @return array<int, string>
     */
    private function postoriusNotices(string $html): array
    {
        preg_match_all('/<div class="alert alert-(\w+)[^>]*>(.*?)<\/div>/s', $html, $alerts, PREG_SET_ORDER);

        return collect($alerts)
            ->reject(fn (array $alert): bool => $alert[1] === 'success')
            ->map(function (array $alert): string {
                $text = html_entity_decode(strip_tags((string) preg_replace('/<button\b.*?<\/button>/s', '', $alert[2])));

                // Collapse whitespace and drop a leftover "×" close mark.
                return trim((string) preg_replace(['/\s+/u', '/\s*×$/u'], [' ', ''], trim($text)));
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Subscriptions Mailman holds back for confirmation or moderator approval.
     *
     * @return array<string, array{token: string, owner: string}> keyed by lowercased address
     */
    private function pendingRequests(string $listId): array
    {
        try {
            $entries = $this->request()->get("lists/{$listId}/requests")->throw()->json('entries', []);
        } catch (Throwable) {
            return [];
        }

        return collect($entries)
            ->where('type', 'subscription')
            ->mapWithKeys(fn (array $entry): array => [strtolower((string) $entry['email']) => [
                'token' => (string) $entry['token'],
                'owner' => (string) ($entry['token_owner'] ?? 'unknown'),
            ]])
            ->all();
    }

    /**
     * A stale pending subscription (e.g. an old, never confirmed self sign-up)
     * makes Mailman refuse the pre-approved one with a 409, so it is dropped
     * first - "discard" sends no notification.
     */
    private function discardPendingRequest(string $listId, string $token, string $owner): void
    {
        $page = $this->postoriusUrl($owner === 'subscriber'
            ? "postorius/lists/{$listId}/pending_confirmation"
            : "postorius/lists/{$listId}/subscription_requests");
        $html = $this->postoriusRequest()->get($page)->throw()->body();
        $handleUrl = $this->postoriusUrl("postorius/lists/{$listId}/handle_subscription_request/{$token}/");
        $response = null;

        // Postorius >= 1.3.6 takes the action as a POSTed form field ...
        if (preg_match('/name="csrfmiddlewaretoken" value="([^"]+)"/', $html, $csrf)) {
            $response = $this->postoriusRequest()
                ->withHeaders(['Referer' => $page])
                ->asForm()
                ->post($handleUrl, [
                    'csrfmiddlewaretoken' => $csrf[1],
                    'discard' => '1',
                    'kind' => $owner === 'subscriber' ? 'confirmation' : 'subscription',
                ]);
        }

        // ... older ones (e.g. 1.3.4) only link to it as a GET path segment, without a form.
        if ($response === null || $response->notFound()) {
            $response = $this->postoriusRequest()->withHeaders(['Referer' => $page])->get($handleUrl.'discard');
        }

        $notices = $this->postoriusNotices($response->throw()->body());
        if ($notices !== []) {
            throw new RuntimeException(implode(' | ', $notices));
        }
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
        $failed = [];
        $submitted = [];
        $notices = [];
        $discardErrors = [];

        try {
            $this->loginToPostorius();
        } catch (Throwable $e) {
            return array_fill_keys($emails, $e->getMessage());
        }

        if ($subscribed) {
            foreach ($this->pendingRequests($listId) as $email => $request) {
                if (in_array($email, array_map(strtolower(...), $emails), true)) {
                    try {
                        $this->discardPendingRequest($listId, $request['token'], $request['owner']);
                    } catch (Throwable $e) {
                        // Still try to subscribe; reported below if that fails too.
                        $discardErrors[$email] = $e->getMessage();
                    }
                }
            }
        }

        // Chunked so each form response stays well within the HTTP timeout.
        foreach (array_chunk($emails, self::POSTORIUS_CHUNK_SIZE) as $chunk) {
            try {
                $chunkNotices = $this->submitPostoriusForm("postorius/lists/{$listId}/{$form}/", ['emails' => implode("\n", $chunk)] + $fields);
                array_push($submitted, ...$chunk);
                foreach ($chunk as $email) {
                    $notices[$email] = $chunkNotices;
                }
            } catch (Throwable $e) {
                $failed += array_fill_keys($chunk, $e->getMessage());
            }
        }

        if ($submitted === []) {
            return $failed;
        }

        try {
            $roster = array_map(strtolower(...), array_column($this->listMembers($listId), 'email'));
        } catch (Throwable $e) {
            return $failed + array_fill_keys($submitted, $e->getMessage());
        }

        $pending = null;
        foreach ($submitted as $email) {
            if (in_array(strtolower($email), $roster, true) === $subscribed) {
                continue;
            }

            $reason = [$subscribed ? 'still not subscribed after the Postorius request' : 'still subscribed after the Postorius request'];

            // Prefer messages naming this address; a chunk's other messages may still explain it.
            $own = array_filter($notices[$email], fn (string $notice): bool => stripos($notice, $email) !== false);
            if ($own !== [] || $notices[$email] !== []) {
                $reason[] = 'Postorius: '.implode(' | ', $own ?: $notices[$email]);
            }

            if ($subscribed) {
                $pending ??= $this->pendingRequests($listId);
                if (isset($pending[strtolower($email)])) {
                    $reason[] = 'pending in Mailman, waiting for '.$pending[strtolower($email)]['owner'];
                }
                if (isset($discardErrors[strtolower($email)])) {
                    $reason[] = 'discarding the pending request failed: '.$discardErrors[strtolower($email)];
                }
            }

            $failed[$email] = implode('; ', $reason);
        }

        return $failed;
    }
}
