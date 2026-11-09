<?php

use App\Ldap\Community;
use App\Livewire\Mailman\ListGroupMailmanLists;
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

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('loadStatuses')
        ->assertSet("statuses.{$mapping->id}.state", 'synced');
});

test('a list with only missing addresses shows as pending', function (): void {
    [$community, $mapping] = mappedListWithOneMember();
    fakeRoster([]);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('loadStatuses')
        ->assertSet("statuses.{$mapping->id}.state", 'pending')
        ->assertSee(__('group_mailman_lists.status_pending'))
        ->assertDontSee(__('group_mailman_lists.status_stale'));
});

test('a list with missing and removable addresses shows as stale with both messages', function (): void {
    [$community, $mapping, $email] = mappedListWithOneMember();
    fakeRoster(['stale@example.org']);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('loadStatuses')
        ->assertSet("statuses.{$mapping->id}.state", 'stale')
        ->assertSee(__('group_mailman_lists.status_pending').', '.__('group_mailman_lists.status_stale'));
});

test('a list whose roster cannot be fetched shows as error', function (): void {
    [$community, $mapping] = mappedListWithOneMember();
    Http::fake(['*' => Http::response([], 500)]);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('loadStatuses')
        ->assertSet("statuses.{$mapping->id}.state", 'error')
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

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('loadStatuses')
        ->assertSet("statuses.{$mapping->id}.state", 'error');
    Http::assertNothingSent();
});

test('another realm\'s mappings are not loaded', function (): void {
    [, $mapping] = mappedListWithOneMember();
    $otherCommunity = newCommunity();
    actingAsAdmin($otherCommunity);
    Http::fake();

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $otherCommunity])
        ->call('loadStatuses')
        ->assertSet('statuses', []);
    Http::assertNothingSent();
});

test('the overview loads the statuses of all rows on the page in one call', function (): void {
    [$community, $mapping, $email] = mappedListWithOneMember();
    fakeRoster([$email]);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertSeeHtml('x-init="$wire.loadStatuses()"')
        ->call('loadStatuses')
        ->assertSet("statuses.{$mapping->id}.state", 'synced')
        ->assertDontSeeHtml('x-init="$wire.loadStatuses()"');
});
