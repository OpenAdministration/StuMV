<?php

use App\Ldap\Community;
use App\Livewire\Mailman\MailmanListStatus;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
});

/**
 * @return array{0: Community, 1: GroupMailmanList, 2: string}
 */
function mappedListWithOneMember(): array
{
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $active = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $active->username,
        'from' => today()->subMonth(),
    ]);
    $mapping = GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);
    actingAsAdmin($community);

    return [$community, $mapping, $active->ldap()->getFirstAttribute('mail')];
}

function fakeRoster(array $emails): void
{
    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response([
            'entries' => array_map(fn (string $email): array => ['email' => $email, 'self_link' => 'http://mailman.test/3.1/members/'.md5($email)], $emails),
        ]),
    ]);
}

test('a list whose roster matches the group shows as synced', function (): void {
    [$community, $mapping, $email] = mappedListWithOneMember();
    fakeRoster([$email]);

    Livewire::withoutLazyLoading()->test(MailmanListStatus::class, ['uid' => $community->getShortCode(), 'mappingId' => $mapping->id])
        ->assertViewHas('state', 'synced');
});

test('a list with missing or stale addresses shows as out of sync', function (): void {
    [$community, $mapping] = mappedListWithOneMember();
    fakeRoster(['stale@example.org']);

    Livewire::withoutLazyLoading()->test(MailmanListStatus::class, ['uid' => $community->getShortCode(), 'mappingId' => $mapping->id])
        ->assertViewHas('state', 'out_of_sync')
        ->assertSee(__('group_mailman_lists.status_out_of_sync', ['pending' => 1, 'stale' => 1]));
});

test('a list whose roster cannot be fetched shows as error', function (): void {
    [$community, $mapping] = mappedListWithOneMember();
    Http::fake(['*' => Http::response([], 500)]);

    Livewire::withoutLazyLoading()->test(MailmanListStatus::class, ['uid' => $community->getShortCode(), 'mappingId' => $mapping->id])
        ->assertViewHas('state', 'error')
        ->assertSee(__('group_mailman_lists.status_fetch_failed'));
});

test('a list whose group no longer exists shows as error without asking Mailman', function (): void {
    $community = newCommunity();
    $mapping = GroupMailmanList::create([
        'realm' => $community->getShortCode(),
        'group_dn' => 'cn=deleted,ou=Groups,ou='.$community->getShortCode().',ou=Communities,dc=stumv,dc=de',
        'mailman_list_id' => 'a.lists.example.org',
    ]);
    actingAsAdmin($community);
    Http::fake();

    Livewire::withoutLazyLoading()->test(MailmanListStatus::class, ['uid' => $community->getShortCode(), 'mappingId' => $mapping->id])
        ->assertViewHas('state', 'error');
    Http::assertNothingSent();
});

test('another realm\'s mapping cannot be queried', function (): void {
    [, $mapping] = mappedListWithOneMember();
    $otherCommunity = newCommunity();

    Livewire::withoutLazyLoading()->test(MailmanListStatus::class, ['uid' => $otherCommunity->getShortCode(), 'mappingId' => $mapping->id])
        ->assertNotFound();
});
