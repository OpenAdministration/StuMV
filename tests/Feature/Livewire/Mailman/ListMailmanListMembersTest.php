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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $community->getShortCode(), 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);
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
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertSeeHtml(route('realms.group-mailman-lists.members', ['realm' => $uid, 'listId' => 'newsletter.lists.example.org']));
});

test('the members can be filtered by sync status', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $synced = TestLdap::member($community);
    $pending = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    foreach ([$synced, $pending] as $member) {
        RoleMembership::create([
            'realm' => $uid,
            'role_cn' => 'mitglied',
            'committee_dn' => $committee->getDn(),
            'username' => $member->username,
            'from' => today()->subMonth(),
        ]);
    }
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);

    $syncedMail = $synced->ldap()->getFirstAttribute('mail');
    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => [
            ['email' => $syncedMail, 'self_link' => 'http://mailman.test/3.1/members/1'],
            ['email' => 'stale@example.com', 'self_link' => 'http://mailman.test/3.1/members/2'],
        ]]),
    ]);

    actingAsAdmin($community);

    $component = Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org']);
    $emailsFor = fn (string $status): array => collect($component->set('status', $status)->viewData('members')->items())->pluck('email')->all();

    expect($emailsFor('synced'))->toBe([$syncedMail])
        ->and($emailsFor('pending'))->toBe([$pending->ldap()->getFirstAttribute('mail')])
        ->and($emailsFor('stale'))->toBe(['stale@example.com'])
        ->and($emailsFor(''))->toHaveCount(3)
        ->and($component->viewData('statusCounts'))->toBe(['synced' => 1, 'pending' => 1, 'stale' => 1]);
});

function memberSyncFixture(): array
{
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $member = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);
    actingAsAdmin($community);

    return [$community, $member->ldap()->getFirstAttribute('mail')];
}

test('a pending member can be subscribed on its own', function (): void {
    [$community, $mail] = memberSyncFixture();
    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
        'mailman.test/3.1/members' => Http::response('', 201),
    ]);

    Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->call('syncMember', $mail)
        ->assertDispatched('toast-show', fn (string $name, array $params): bool => $params['dataset']['variant'] === 'success');

    Http::assertSent(fn ($request): bool => $request->url() === 'http://mailman.test/3.1/members' && $request['subscriber'] === $mail);
});

test('a stale member can be removed on its own', function (): void {
    [$community] = memberSyncFixture();
    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => [
            ['email' => 'stale@example.com', 'self_link' => 'http://mailman.test/3.1/members/42'],
        ]]),
        'mailman.test/3.1/members/42' => Http::response('', 204),
    ]);

    Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->call('syncMember', 'stale@example.com')
        ->assertDispatched('toast-show', fn (string $name, array $params): bool => $params['dataset']['variant'] === 'success');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && $request->url() === 'http://mailman.test/3.1/members/42');
});

test('an address outside the group cannot be subscribed via the single sync', function (): void {
    [$community] = memberSyncFixture();
    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => []]),
    ]);

    Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->call('syncMember', 'intruder@example.com');

    Http::assertNotSent(fn ($request): bool => $request->method() !== 'GET');
});

test('each member row lists the member groups the person comes from', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $boardRole = TestLdap::makeRole($committee, 'vorstand');
    $newsletter = TestLdap::makeGroup($community, 'newsletter');
    $board = TestLdap::makeGroup($community, 'board');
    $both = TestLdap::member($community);
    $onlyNewsletter = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $newsletter->getDn(), 'role_dn' => $role->getDn()]);
    GroupMembership::create(['group_dn' => $board->getDn(), 'role_dn' => $boardRole->getDn()]);
    foreach ([[$both, 'mitglied'], [$both, 'vorstand'], [$onlyNewsletter, 'mitglied']] as [$user, $roleCn]) {
        RoleMembership::create(['realm' => $uid, 'role_cn' => $roleCn, 'committee_dn' => $committee->getDn(), 'username' => $user->username, 'from' => today()->subMonth()]);
    }
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$newsletter->getDn(), $board->getDn()]);
    Http::fake([
        'mailman.test/3.1/lists/newsletter.lists.example.org/roster/member' => Http::response(['entries' => [
            ['email' => 'stale@example.org', 'self_link' => 'http://mailman.test/3.1/members/9'],
        ]]),
    ]);
    actingAsAdmin($community);

    $rows = collect(Livewire::test(ListMailmanListMembers::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->viewData('members')->items())->keyBy('email');

    expect($rows[$both->ldap()->getFirstAttribute('mail')]['groups'])->toEqualCanonicalizing(['newsletter', 'board'])
        ->and($rows[$onlyNewsletter->ldap()->getFirstAttribute('mail')]['groups'])->toBe(['newsletter'])
        ->and($rows['stale@example.org']['groups'])->toBe([]);
});
