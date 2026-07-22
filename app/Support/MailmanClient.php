<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Mailman 3 Core's own REST API (not Postorius/Hyperkitty
 * - see config/services.php's "mailman.url", e.g. http://host:8001/3.1).
 */
class MailmanClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiUser,
        private readonly string $apiKey,
    ) {}

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
    public function unsubscribe(string $selfLink): void
    {
        $this->request()->delete($selfLink)->throw();
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->apiUser, $this->apiKey)
            ->baseUrl(rtrim($this->baseUrl, '/').'/')
            ->timeout(10);
    }
}
