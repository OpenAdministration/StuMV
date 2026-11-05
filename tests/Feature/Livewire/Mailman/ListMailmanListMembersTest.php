<?php

use App\Livewire\Mailman\ListGroupMailmanLists;
use App\Livewire\Mailman\ListMailmanListMembers;
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

test('a desired member already on the Mailman roster shows as synced', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $active = TestLdap::member($community);
    $email = $active->ldap()->getFirstAttribute('mail');

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $active->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response([
            'entries' => [
                ['email' => $email, 'self_link' => 'http://mailman.test/3.1/members/1'],
            ],
        ]),
    ]);

    actingAsAdmin($community);

    $rows = Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->assertSee($email)
        ->viewData('members');

    expect(collect($rows->items())->firstWhere(fn (array $row) => $row['email'] === $email)['status'])->toBe('synced');
});

test('a desired member not yet on the Mailman roster shows as pending', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $active = TestLdap::member($community);
    $email = $active->ldap()->getFirstAttribute('mail');

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $active->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    actingAsAdmin($community);

    $rows = Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->assertSee($email)
        ->viewData('members');

    expect(collect($rows->items())->firstWhere(fn (array $row) => $row['email'] === $email)['status'])->toBe('pending');
});

test('a Mailman member without a backing desired membership shows as stale', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response([
            'entries' => [
                ['email' => 'stale@example.com', 'self_link' => 'http://mailman.test/3.1/members/42'],
            ],
        ]),
    ]);

    actingAsAdmin($community);

    $rows = Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->assertSee('stale@example.com')
        ->viewData('members');

    expect(collect($rows->items())->firstWhere(fn (array $row) => $row['email'] === 'stale@example.com')['status'])->toBe('stale');
});

test('the explanation names the group mapped to the list', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'grp-a');
    GroupMailmanList::create(['realm' => $community->getShortCode(), 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    actingAsAdmin($community);

    Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->assertSee(__('group_mailman_lists.members_explanation', ['group' => 'grp-a']));
});

test('the member search filters the list', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $alpha = TestLdap::member($community);
    $beta = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    foreach ([$alpha, $beta] as $member) {
        RoleMembership::create([
            'realm' => $uid,
            'role_cn' => 'mitglied',
            'committee_dn' => $committee->getDn(),
            'username' => $member->username,
            'from' => today()->subMonth(),
        ]);
    }
    $alpha->ldap()->fill(['cn' => 'Alpha Alison'])->save();
    $beta->ldap()->fill(['cn' => 'Beta Baker'])->save();
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    actingAsAdmin($community);

    Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->set('search', 'Alpha')
        ->assertSee('Alpha Alison')
        ->assertDontSee('Beta Baker');
});

test('a list with no members shows a warning callout', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    actingAsAdmin($community);

    Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->assertSeeHtml('data-flux-callout');
});

test('a failed Mailman fetch is shown with an unknown status instead of a wrong verdict', function (): void {
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
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response('', 500),
    ]);

    actingAsAdmin($community);

    $rows = Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->assertSeeText(__('group_mailman_lists.members_fetch_failed'))
        ->viewData('members');

    expect(collect($rows->items())->first()['status'])->toBe('unknown');
});

test('a non-admin cannot view the member status page', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);
    actingAsModerator($community);

    $this->get(route('realms.group-mailman-lists.members', ['realm' => $uid, 'listId' => 'newsletter.lists.example.org']))->assertForbidden();
});

test('viewing the status of a list with no mapping 404s', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $this->get(route('realms.group-mailman-lists.members', ['realm' => $community->getShortCode(), 'listId' => 'unmapped.lists.example.org']))->assertNotFound();
});

test('the mapping list links to the member status page', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertSeeHtml(route('realms.group-mailman-lists.members', ['realm' => $uid, 'listId' => 'newsletter.lists.example.org']));
});
