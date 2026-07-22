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
    config(['services.mailman.url' => 'http://mailman.test/3.1']);
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

test('members are shown with every mapped group that grants them access', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committeeA = TestLdap::makeCommittee($community, 'fsr');
    $committeeB = TestLdap::makeCommittee($community, 'asta');
    $roleA = TestLdap::makeRole($committeeA, 'mitglied');
    $roleB = TestLdap::makeRole($committeeB, 'mitglied');
    $groupA = TestLdap::makeGroup($community, 'grp-a');
    $groupB = TestLdap::makeGroup($community, 'grp-b');
    $memberA = TestLdap::member($community);
    $memberB = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $groupA->getDn(), 'role_dn' => $roleA->getDn()]);
    GroupMembership::create(['group_dn' => $groupB->getDn(), 'role_dn' => $roleB->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committeeA->getDn(),
        'username' => $memberA->username,
        'from' => today()->subMonth(),
    ]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committeeB->getDn(),
        'username' => $memberB->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $groupA->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $groupB->getDn(), 'mailman_list_id' => 'newsletter.lists.example.org']);

    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    actingAsAdmin($community);

    $rows = Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->viewData('members');

    $emails = collect($rows->items())->pluck('email')->all();
    expect($emails)->toContain($memberA->ldap()->getFirstAttribute('mail'))
        ->and($emails)->toContain($memberB->ldap()->getFirstAttribute('mail'));

    expect(collect($rows->items())->firstWhere(fn (array $row) => $row['email'] === $memberA->ldap()->getFirstAttribute('mail'))['groups'])
        ->toBe(['grp-a']);
    expect(collect($rows->items())->firstWhere(fn (array $row) => $row['email'] === $memberB->ldap()->getFirstAttribute('mail'))['groups'])
        ->toBe(['grp-b']);
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
