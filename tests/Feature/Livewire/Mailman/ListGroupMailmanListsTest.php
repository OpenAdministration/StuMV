<?php

use App\Livewire\Mailman\ListGroupMailmanLists;
use App\Models\GroupMailmanList;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('deletePrepare', $mapping->id);
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
