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
        ->set('member_groups', ['newsletter'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.group-mailman-lists', ['realm' => $community->getShortCode()]));

    $mapping = GroupMailmanList::where('mailman_list_id', 'newsletter.lists.example.org')->firstOrFail();

    expect($mapping->realm)->toBe($community->getShortCode())
        ->and($mapping->groupDns('member'))->toBe([$group->getDn()])
        ->and($mapping->groupDns('moderator'))->toBe([]);
});

test('a list that is already mapped cannot be mapped a second time', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    TestLdap::makeGroup($community, 'digest');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('member_groups', ['newsletter'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('member_groups', ['digest'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasErrors(['mailman_list_id' => 'unique']);

    expect(GroupMailmanList::count())->toBe(1);
});

test('a list mapped in another realm cannot be mapped again', function (): void {
    $other = newCommunity();
    GroupMailmanList::createForGroups(['realm' => $other->getShortCode(), 'mailman_list_id' => 'newsletter.lists.example.org'], ['cn=newsletter,ou=Groups,ou='.$other->getShortCode().',ou=Communities,dc=stumv,dc=de']);

    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('member_groups', ['newsletter'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasErrors(['mailman_list_id' => 'unique']);
});

test('mapping the same group to the same list twice is rejected', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('member_groups', ['newsletter'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('member_groups', ['newsletter'])
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
        ->set('member_groups', ['newsletter'])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('member_groups', ['newsletter'])
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
        ->assertHasErrors(['member_groups' => 'required']);
});

test('mapping requires a mailman list id', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('member_groups', ['newsletter'])
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

test('a mapping can have several groups per role', function (): void {
    $community = newCommunity();
    $groups = collect(['newsletter', 'digest', 'board', 'mods'])->mapWithKeys(fn (string $cn) => [$cn => TestLdap::makeGroup($community, $cn)]);
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->set('member_groups', ['newsletter', 'digest'])
        ->set('moderator_groups', ['mods', 'board'])
        ->call('save')
        ->assertHasNoErrors();

    $mapping = GroupMailmanList::where('mailman_list_id', 'newsletter.lists.example.org')->firstOrFail();
    expect($mapping->groupDns('member'))->toEqualCanonicalizing([$groups['newsletter']->getDn(), $groups['digest']->getDn()])
        ->and($mapping->groupDns('moderator'))->toEqualCanonicalizing([$groups['mods']->getDn(), $groups['board']->getDn()]);
});

test('groups from outside the realm are rejected', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('mailman_list_id', 'newsletter.lists.example.org')
        ->set('member_groups', ['newsletter'])
        ->set('moderator_groups', ['unknown'])
        ->call('save')
        ->assertHasErrors(['moderator_groups.0']);
});

test('a mapping can be edited', function (): void {
    $community = newCommunity();
    $newsletter = TestLdap::makeGroup($community, 'newsletter');
    $board = TestLdap::makeGroup($community, 'board');
    $mapping = GroupMailmanList::createForGroups(['realm' => $community->getShortCode(), 'mailman_list_id' => 'newsletter.lists.example.org'], [$newsletter->getDn()]);
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community, 'listId' => 'newsletter.lists.example.org'])
        ->assertSet('member_groups', ['newsletter'])
        ->set('mailman_list_id', 'renamed.lists.example.org')
        ->set('moderator_groups', ['board'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.group-mailman-lists', ['realm' => $community->getShortCode()]));

    $mapping->refresh();
    expect($mapping->mailman_list_id)->toBe('renamed.lists.example.org')
        ->and($mapping->groupDns('member'))->toBe([$newsletter->getDn()])
        ->and($mapping->groupDns('moderator'))->toBe([$board->getDn()])
        ->and(GroupMailmanList::count())->toBe(1);
});

test('another realm\'s mapping cannot be edited', function (): void {
    $other = newCommunity();
    GroupMailmanList::createForGroups(['realm' => $other->getShortCode(), 'mailman_list_id' => 'other.lists.example.org'], ['cn=x,ou=Groups,ou='.$other->getShortCode().',ou=Communities,dc=stumv,dc=de']);
    $community = newCommunity();
    actingAsAdmin($community);

    $this->get(route('realms.group-mailman-lists.edit', ['realm' => $community->getShortCode(), 'listId' => 'other.lists.example.org']))->assertNotFound();
});
