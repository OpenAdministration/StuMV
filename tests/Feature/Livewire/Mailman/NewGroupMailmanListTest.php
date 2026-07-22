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
        ->set('group_cns', ['newsletter'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.group-mailman-lists', ['realm' => $community->getShortCode()]));

    $mapping = GroupMailmanList::where('mailman_list_id', 'newsletter.lists.example.org')->firstOrFail();

    expect($mapping->realm)->toBe($community->getShortCode())
        ->and($mapping->group_dn)->toBe($group->getDn());
});

test('several groups can be mapped to a mailman list at once', function (): void {
    $community = newCommunity();
    $newsletter = TestLdap::makeGroup($community, 'newsletter');
    $digest = TestLdap::makeGroup($community, 'digest');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cns', ['newsletter', 'digest'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.group-mailman-lists', ['realm' => $community->getShortCode()]));

    expect(GroupMailmanList::count())->toBe(2)
        ->and(GroupMailmanList::where('group_dn', $newsletter->getDn())->exists())->toBeTrue()
        ->and(GroupMailmanList::where('group_dn', $digest->getDn())->exists())->toBeTrue();
});

test('mapping the same group to the same list twice is rejected', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cns', ['newsletter'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cns', ['newsletter'])
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
        ->set('group_cns', ['newsletter'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cns', ['newsletter'])
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
        ->assertHasErrors(['group_cns' => 'required']);
});

test('mapping requires a mailman list id', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('group_cns', ['newsletter'])
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
