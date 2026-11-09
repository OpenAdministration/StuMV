<?php

use App\Livewire\Mailman\ListGroupMailmanLists;
use App\Models\GroupMailmanList;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('only this realm\'s mappings are listed, not another realm\'s', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    $otherGroup = TestLdap::makeGroup($otherCommunity, 'other-list');
    GroupMailmanList::create(['realm' => $community->getShortCode(), 'group_dn' => $group->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    GroupMailmanList::create(['realm' => $otherCommunity->getShortCode(), 'group_dn' => $otherGroup->getDn(), 'mailman_list_id' => 'b.lists.example.org']);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertSee('a.lists.example.org')
        ->assertDontSee('b.lists.example.org');
});

test('a mapping shows a warning badge when its group no longer exists', function (): void {
    $community = newCommunity();
    GroupMailmanList::create([
        'realm' => $community->getShortCode(),
        'group_dn' => 'cn=deleted,ou=Groups,ou='.$community->getShortCode().',ou=Communities,dc=stumv,dc=de',
        'mailman_list_id' => 'a.lists.example.org',
    ]);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertSee(__('group_mailman_lists.group_missing'));
});

test('the search filters by list id and by group short name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $newsletter = TestLdap::makeGroup($community, 'newsletter');
    $vorstand = TestLdap::makeGroup($community, 'vorstand');
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $newsletter->getDn(), 'mailman_list_id' => 'info.lists.example.org']);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $vorstand->getDn(), 'mailman_list_id' => 'board.lists.example.org']);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->set('search', 'board')
        ->assertSee('board.lists.example.org')
        ->assertDontSee('info.lists.example.org')
        ->set('search', 'newslet')
        ->assertSee('info.lists.example.org')
        ->assertDontSee('board.lists.example.org');
});

test('the mapping list can be sorted by list id and by group', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $alpha = TestLdap::makeGroup($community, 'alpha');
    $zulu = TestLdap::makeGroup($community, 'zulu');
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $zulu->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $alpha->getDn(), 'mailman_list_id' => 'b.lists.example.org']);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertSeeInOrder(['a.lists.example.org', 'b.lists.example.org'])
        ->call('sortBy', 'mailman_list_id')
        ->assertSeeInOrder(['b.lists.example.org', 'a.lists.example.org'])
        ->call('sortBy', 'group')
        ->assertSeeInOrder(['b.lists.example.org', 'a.lists.example.org'])
        ->call('sortBy', 'group')
        ->assertSeeInOrder(['a.lists.example.org', 'b.lists.example.org']);
});

test('the mapping list is paginated', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    foreach (range(1, 11) as $i) {
        GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => sprintf('list%02d.lists.example.org', $i)]);
    }
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertSee('list10.lists.example.org')
        ->assertDontSee('list11.lists.example.org')
        ->call('gotoPage', 2)
        ->assertSee('list11.lists.example.org');
});

test('a single list can be synced from the overview', function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
    $community = newCommunity();
    $uid = $community->getShortCode();
    $group = TestLdap::makeGroup($community, 'newsletter');
    $mapping = GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    GroupMailmanList::create(['realm' => $uid, 'group_dn' => $group->getDn(), 'mailman_list_id' => 'b.lists.example.org']);
    actingAsAdmin($community);

    Http::fake([
        'mailman.test/3.1/lists/a.lists.example.org/roster/member' => Http::response([
            'entries' => [['email' => 'stale@example.com', 'self_link' => 'http://mailman.test/3.1/members/42']],
        ]),
        'mailman.test/3.1/members/42' => Http::response('', 204),
    ]);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('syncList', $mapping->id)
        ->assertDispatched('toast-show', fn (string $name, array $params): bool => $params['dataset']['variant'] === 'success');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && $request->url() === 'http://mailman.test/3.1/members/42');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'b.lists.example.org'));
});

test('a failed sync shows an error toast', function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    $mapping = GroupMailmanList::create(['realm' => $community->getShortCode(), 'group_dn' => $group->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    actingAsAdmin($community);

    Http::fake(['mailman.test/*' => Http::response('', 500)]);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('syncList', $mapping->id)
        ->assertDispatched('toast-show', fn (string $name, array $params): bool => $params['dataset']['variant'] === 'danger');
});

test('an admin cannot sync another realm\'s list', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $otherGroup = TestLdap::makeGroup($otherCommunity, 'other-list');
    $mapping = GroupMailmanList::create(['realm' => $otherCommunity->getShortCode(), 'group_dn' => $otherGroup->getDn(), 'mailman_list_id' => 'b.lists.example.org']);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->instance()->syncList($mapping->id);
})->throws(ModelNotFoundException::class);

test('a mapping can be deleted', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    $mapping = GroupMailmanList::create(['realm' => $community->getShortCode(), 'group_dn' => $group->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('deletePrepare', $mapping->id)
        ->call('deleteCommit');

    expect(GroupMailmanList::find($mapping->id))->toBeNull();
});

test('an admin cannot delete another realm\'s mapping', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $otherGroup = TestLdap::makeGroup($otherCommunity, 'other-list');
    $mapping = GroupMailmanList::create(['realm' => $otherCommunity->getShortCode(), 'group_dn' => $otherGroup->getDn(), 'mailman_list_id' => 'b.lists.example.org']);
    actingAsAdmin($community);

    // Called on the instance: Livewire's test harness renders
    // ModelNotFoundException as a 404 instead of propagating it.
    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->instance()->deletePrepare($mapping->id);
})->throws(ModelNotFoundException::class);

test('a non-admin cannot view the mapping list', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $this->get(route('realms.group-mailman-lists', ['realm' => $community->getShortCode()]))->assertForbidden();
});

test('the admin realm has no group-mailman-list feature', function (): void {
    actingAsSuperAdmin();

    $this->get(route('realms.group-mailman-lists', ['realm' => 'admin']))->assertNotFound();
});

test('a list can be disabled and enabled again', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    $mapping = GroupMailmanList::create(['realm' => $community->getShortCode(), 'group_dn' => $group->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    actingAsAdmin($community);

    $component = Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('toggleEnabled', $mapping->id)
        ->assertSee(__('group_mailman_lists.disabled'));
    expect($mapping->fresh()->enabled)->toBeFalse();

    $component->call('toggleEnabled', $mapping->id)
        ->assertDontSee(__('group_mailman_lists.disabled'));
    expect($mapping->fresh()->enabled)->toBeTrue();
});

test('a disabled list is not synced from the overview', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    $mapping = GroupMailmanList::create(['realm' => $community->getShortCode(), 'group_dn' => $group->getDn(), 'mailman_list_id' => 'a.lists.example.org', 'enabled' => false]);
    actingAsAdmin($community);
    Http::fake();

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('syncList', $mapping->id);

    Http::assertNothingSent();
});

test('another realm\'s list cannot be toggled', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $otherGroup = TestLdap::makeGroup($otherCommunity, 'other');
    $mapping = GroupMailmanList::create(['realm' => $otherCommunity->getShortCode(), 'group_dn' => $otherGroup->getDn(), 'mailman_list_id' => 'b.lists.example.org']);
    actingAsAdmin($community);

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('toggleEnabled', $mapping->id)
        ->assertNotFound();
    expect($mapping->fresh()->enabled)->toBeTrue();
});

test('the list id links to Postorius only when the web url is configured', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    GroupMailmanList::create(['realm' => $community->getShortCode(), 'group_dn' => $group->getDn(), 'mailman_list_id' => 'a.lists.example.org']);
    actingAsAdmin($community);

    config(['services.mailman.web_url' => null]);
    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertDontSee('/postorius/lists/');

    config(['services.mailman.web_url' => 'https://lists.example.org/']);
    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->assertSeeHtml('href="https://lists.example.org/postorius/lists/a.lists.example.org/"');
});
