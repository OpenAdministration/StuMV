<?php

use App\Livewire\Mailman\NewGroupMailmanList;
use App\Models\GroupMailmanList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a group can be mapped to a mailman list', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'newsletter')
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.group-mailman-lists', ['realm' => $community->getShortCode()]));

    $mapping = GroupMailmanList::where('mailman_list_id', 'newsletter.lists.example.org')->firstOrFail();

    expect($mapping->realm)->toBe($community->getShortCode())
        ->and($mapping->group_dn)->toBe($group->getDn());
});

test('a list that already has a group cannot get a second one', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    TestLdap::makeGroup($community, 'digest');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'newsletter')
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'digest')
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasErrors(['mailman_list_id' => 'unique']);

    expect(GroupMailmanList::count())->toBe(1);
});

test('a list mapped in another realm cannot be mapped again', function (): void {
    $other = newCommunity();
    GroupMailmanList::create(['realm' => $other->getShortCode(), 'group_dn' => 'cn=newsletter,ou=Groups,ou='.$other->getShortCode().',ou=Communities,dc=stumv,dc=de', 'mailman_list_id' => 'newsletter.lists.example.org']);

    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'newsletter')
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasErrors(['mailman_list_id' => 'unique']);
});

test('mapping the same group to the same list twice is rejected', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'newsletter')
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'newsletter')
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasErrors(['mailman_list_id']);

    expect(GroupMailmanList::count())->toBe(1);
});

test('the same group can be mapped to two different lists', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'newsletter')
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'newsletter')
        ->set('mailman_list_id', 'digest.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    expect(GroupMailmanList::count())->toBe(2);
});

test('mapping requires a group', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasErrors(['group_cn' => 'required']);
});

test('mapping requires a mailman list id', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cn', 'newsletter')
        ->call('save')
        ->assertHasErrors(['mailman_list_id' => 'required']);
});

test('a non-admin cannot create a mapping', function (): void {
    $community = newCommunity();
    actingAsModerator($community);

    $this->get(route('realms.group-mailman-lists.new', ['realm' => $community->getShortCode()]))->assertForbidden();
});

test('the admin realm has no group-mailman-list feature', function (): void {
    actingAsSuperAdmin();

    $this->get(route('realms.group-mailman-lists.new', ['realm' => 'admin']))->assertNotFound();
});
