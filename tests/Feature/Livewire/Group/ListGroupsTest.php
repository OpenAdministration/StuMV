<?php

use App\Ldap\Group;
use App\Livewire\Group\EditGroup;
use App\Livewire\Group\ListGroups;
use App\Livewire\Group\NewGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the list, create and edit pages label the description field "Description", not "Full Name"/"Full Groupname"', function (): void {
    // Force English so the labels render as the literal, untranslated keys -
    // in German "Full Name" itself renders as just "Name" (still used
    // elsewhere, e.g. edit-role.blade.php), which would be too generic a
    // substring to safely assert the absence of.
    app()->setLocale('en');
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->assertSee('Description')
        ->assertDontSee('Full Name');

    Livewire::test(NewGroup::class, ['uid' => $community])
        ->assertSee('Description')
        ->assertDontSee('Full Groupname');

    Livewire::test(EditGroup::class, ['uid' => $community, 'cn' => 'newsletter'])
        ->assertSee('Description')
        ->assertDontSee('Full Groupname');
});

test('an admin can delete a group', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->call('deletePrepare', $uid, 'newsletter')
        ->set('deleteConfirmText', 'newsletter')
        ->call('deleteCommit');

    expect(Group::find(Group::dnFrom($uid, 'newsletter')))->toBeNull();
});

test('deletePrepare fills in the group name for the confirmation modal', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->call('deletePrepare', $uid, 'newsletter')
        ->assertSet('deleteGroupName', 'newsletter')
        ->assertSee('newsletter');
});

test('groups are listed without using the LDAP slice/VLV query', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'grp1');
    TestLdap::makeGroup($community, 'grp2');
    actingAsModerator($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->assertSee('grp1')
        ->assertSee('grp2');
});

test('the group search filters the list', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'alpha');
    TestLdap::makeGroup($community, 'beta');
    actingAsModerator($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->set('search', 'alpha')
        ->assertSee('alpha')
        ->assertDontSee('beta');
});

test('the group search also matches the description', function (): void {
    $community = newCommunity();
    $alpha = TestLdap::makeGroup($community, 'alpha');
    $alpha->fill(['description' => 'Newsletter editors'])->save();
    TestLdap::makeGroup($community, 'beta');
    actingAsModerator($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->set('search', 'newsletter')
        ->assertSee('alpha')
        ->assertDontSee('beta');
});

test('groups are sorted by cn ascending by default', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'zeta');
    TestLdap::makeGroup($community, 'alpha');
    TestLdap::makeGroup($community, 'mike');
    actingAsModerator($community);

    $cns = Livewire::test(ListGroups::class, ['uid' => $community])
        ->viewData('groups')
        ->map(fn ($group) => $group->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['alpha', 'mike', 'zeta']);
});

test('sortBy toggles direction and re-sorts the groups descending', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'zeta');
    TestLdap::makeGroup($community, 'alpha');
    TestLdap::makeGroup($community, 'mike');
    actingAsModerator($community);

    $cns = Livewire::test(ListGroups::class, ['uid' => $community])
        ->call('sortBy', 'cn')
        ->assertSet('sortDirection', 'desc')
        ->viewData('groups')
        ->map(fn ($group) => $group->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['zeta', 'mike', 'alpha']);
});

test('the groups list is paginated to 10 per page', function (): void {
    $community = newCommunity();
    foreach (range(1, 15) as $i) {
        TestLdap::makeGroup($community, sprintf('grp%02d', $i));
    }
    actingAsModerator($community);

    $component = Livewire::test(ListGroups::class, ['uid' => $community]);
    $page1Cns = $component->viewData('groups')
        ->map(fn ($group) => $group->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($page1Cns)->toHaveCount(10);

    $page2Cns = $component->call('gotoPage', 2)
        ->viewData('groups')
        ->map(fn ($group) => $group->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($page2Cns)->toHaveCount(5);
    expect(array_intersect($page1Cns, $page2Cns))->toBeEmpty();
});
