<?php

namespace App\Support;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
     */
    public function subscribe(string $listId, string $email): void
    {
        if ($this->usesPostorius()) {
            $this->submitPostoriusForm("postorius/lists/{$listId}/mass_subscribe/", [
                'emails' => $email,
                'pre_verified' => 'on',
                'pre_confirmed' => 'on',
                'pre_approved' => 'on',
            ]);
            $this->assertMembership($listId, $email, true);

            return;
        }

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
    }

    /**
     * $selfLink is the absolute member resource URL returned by
     * listMembers() (e.g. "http://host:8001/3.1/members/123") - passing it
     * straight back avoids having to know Mailman's member-id URL scheme.
     */
    public function unsubscribe(string $listId, string $email, string $selfLink): void
    {
        if ($this->usesPostorius()) {
            $this->submitPostoriusForm("postorius/lists/{$listId}/mass_removal/", ['emails' => $email]);
            $this->assertMembership($listId, $email, false);

            return;
        }

        $this->request()->delete($selfLink)->throw();
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
        return Http::withOptions(['verify' => $this->webVerifyTls, 'cookies' => $this->postoriusCookies])
            ->timeout(10);
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
     * Postorius reports per-address problems only as (localized) flash
     * messages, so the roster is the reliable check.
     */
    private function assertMembership(string $listId, string $email, bool $expected): void
    {
        $emails = array_map(strtolower(...), array_column($this->listMembers($listId), 'email'));

        if (in_array(strtolower($email), $emails, true) !== $expected) {
            throw new RuntimeException($expected
                ? "{$email} is still not subscribed after the Postorius request"
                : "{$email} is still subscribed after the Postorius request");
        }
    }
}
