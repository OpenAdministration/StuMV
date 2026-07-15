<?php

use App\Http\Middleware\SuperAdminMiddleware;
use App\Livewire\AddSuperAdmins;
use App\Livewire\Committee\AddUserToRole;
use App\Livewire\Committee\EditCommittee;
use App\Livewire\Committee\EditRole;
use App\Livewire\Committee\EditRoleMembership;
use App\Livewire\Committee\ListCommitteeModerators;
use App\Livewire\Committee\ListCommitteesTree;
use App\Livewire\Committee\ListRoleMembers;
use App\Livewire\Committee\ListRoles;
use App\Livewire\Committee\NewCommittee;
use App\Livewire\Committee\NewCommitteeModerator;
use App\Livewire\Committee\NewRole;
use App\Livewire\Committee\TerminateRoleMemberships;
use App\Livewire\Group\AddRoleToGroup;
use App\Livewire\Group\EditGroup;
use App\Livewire\Group\ListGroupMembers;
use App\Livewire\Group\ListGroups;
use App\Livewire\Group\ListRolesInGroup;
use App\Livewire\Group\NewGroup;
use App\Livewire\ListSuperUsers;
use App\Livewire\Profile\Memberships;
use App\Livewire\Profile\Picture;
use App\Livewire\Profile\Profile;
use App\Livewire\Realm\CommunityDashboard;
use App\Livewire\Realm\EditRealm;
use App\Livewire\Realm\ListAdmins;
use App\Livewire\Realm\ListApiClients;
use App\Livewire\Realm\ListDomains;
use App\Livewire\Realm\ListMembers;
use App\Livewire\Realm\ListModerators;
use App\Livewire\Realm\ListRealms;
use App\Livewire\Realm\NewAdmin;
use App\Livewire\Realm\NewApiClient;
use App\Livewire\Realm\NewDomain;
use App\Livewire\Realm\NewMember;
use App\Livewire\Realm\NewModerator;
use App\Livewire\Realm\NewRealm;
use App\Livewire\Tools\CompareEmailList;
use App\Livewire\Tools\ImportUsersFromUniLdap;
use App\Livewire\Tools\ToolsDashboard;
use App\Livewire\Tools\UnusedRoles;
use App\Livewire\Tools\UsersNotInUniLdap;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

// Set language based on the user's preferences
$availableLanguages = ['de', 'en'];
$lang = Request::getPreferredLanguage($availableLanguages);
if ($lang) {
    Config::set('app.locale', $lang);
}

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {

    Route::get('/', static fn () => redirect(RouteServiceProvider::home()));
    Route::livewire('/profile/{username}', Profile::class)->name('profile');
    Route::livewire('/profile/{username}/memberships', Memberships::class)->name('profile.memberships');
    Route::livewire('/profile/{username}/picture', Picture::class)->name('profile.picture');
    Route::livewire('/pick-realm', ListRealms::class)->name('realms.pick');

    Route::middleware(['communityMember'])->group(function (): void {
        // member
        Route::livewire('{realm}/dashboard', CommunityDashboard::class)->name('realms.dashboard');
        Route::livewire('{realm}/members/', ListMembers::class)->name('realms.members');
        Route::livewire('{realm}/mods/', ListModerators::class)->name('realms.mods');
        Route::livewire('{realm}/admins/', ListAdmins::class)->name('realms.admins');
        Route::livewire('{realm}/committees', ListCommitteesTree::class)->name('committees.list');
        Route::livewire('{realm}/committees/{ou}', ListRoles::class)->name('committees.roles');
        Route::livewire('{realm}/committees/{ou}/role/{cn}', ListRoleMembers::class)->name('committees.roles.members');
        Route::livewire('{realm}/committees/{ou}/moderators', ListCommitteeModerators::class)->name('committees.moderators');
        // end member
    });

    // committee/community moderators only - role and role-membership actions
    // within a committee (or one moderated by an ancestor of it)
    Route::middleware(['communityMod'])->group(function (): void {
        // mod
        Route::livewire('{realm}/committees/{ou}/new-role', NewRole::class)->name('committees.roles.new');
        Route::livewire('{realm}/committees/{ou}/role/{cn}/edit', EditRole::class)->name('committees.roles.edit');
        Route::livewire('{realm}/committees/{ou}/role/{cn}/new-member', AddUserToRole::class)->name('committees.roles.add-member');
        Route::livewire('{realm}/committees/{ou}/role/{cn}/terminate-memberships', TerminateRoleMemberships::class)->name('committees.roles.terminate-memberships');
        Route::livewire('{realm}/committees/{ou}/role/{cn}/membership/{id}', EditRoleMembership::class)->name('committees.roles.members.edit');
        Route::livewire('{realm}/committees/{ou}/new-moderator', NewCommitteeModerator::class)->name('committees.moderators.new');
        // end mod
    });

    // community moderators only - committees themselves are not delegable to
    // committee moderators, unlike their roles/role-memberships above
    Route::livewire('{realm}/new-committee', NewCommittee::class)->name('committees.new')
        ->can('moderator', 'realm');
    Route::livewire('{realm}/committees/{ou}/edit', EditCommittee::class)->name('committees.edit')
        ->can('moderator', 'realm');

    // mods, admins and superadmins
    Route::middleware(['can:tools,realm'])->group(function (): void {
        Route::livewire('{realm}/tools', ToolsDashboard::class)->name('tools.dashboard');
        Route::livewire('{realm}/tools/compare-email-list', CompareEmailList::class)->name('tools.compare-email-list');
        Route::livewire('{realm}/tools/import-user-uni-ldap', ImportUsersFromUniLdap::class)->name('tools.import-user-uni-ldap');
        Route::livewire('{realm}/tools/users-not-in-uni-ldap', UsersNotInUniLdap::class)->name('tools.users-not-in-uni-ldap');
        Route::livewire('{realm}/tools/unused-roles', UnusedRoles::class)->name('tools.unused-roles');
    });

    Route::middleware(['communityAdmin'])->group(function (): void {
        // admin
        Route::livewire('{realm}/new-admin', NewAdmin::class)->name('realms.admins.new');
        Route::livewire('{realm}/groups', ListGroups::class)->name('realms.groups');
        Route::livewire('{realm}/groups/{cn}/edit', EditGroup::class)->name('realms.groups.edit');
        Route::livewire('{realm}/new-group', NewGroup::class)->name('realms.groups.new');
        Route::livewire('{realm}/group/{cn}/roles', ListRolesInGroup::class)->name('realms.groups.roles');
        Route::livewire('{realm}/group/{cn}/add-role', AddRoleToGroup::class)->name('realms.groups.roles.add');
        Route::livewire('{realm}/group/{cn}/members', ListGroupMembers::class)->name('realms.groups.members');
        Route::livewire('{realm}/domains', ListDomains::class)->name('realms.domains');
        Route::livewire('{realm}/new-domain', NewDomain::class)->name('realms.domains.new');
        Route::livewire('{realm}/api-clients', ListApiClients::class)->name('realms.api-clients');
        Route::livewire('{realm}/new-api-client', NewApiClient::class)->name('realms.api-clients.new');
        Route::livewire('{realm}/edit', EditRealm::class)->name('realms.edit');
        // end admin
    });

    // fine grained permissions
    Route::livewire('{realm}/new-mod', NewModerator::class)->name('realms.mods.new')
        ->can('add_moderator', 'realm');

    Route::middleware([SuperAdminMiddleware::class])->group(function (): void {
        Route::livewire('{realm}/new-member', NewMember::class)->name('realms.members.new');
        Route::livewire('superadmins', ListSuperUsers::class)->name('superadmins.list');
        Route::livewire('add-superadmins', AddSuperAdmins::class)->name('superadmins.add');
        Route::livewire('new-realm', NewRealm::class)->name('realms.new');
    });
    // end auth verified
});

// guest routes
Route::get('about', fn () => redirect(config('app.about_url')))->name('about');

Route::get('privacy', fn () => redirect(config('app.privacy_url')))->name('privacy');

Route::get('terms', fn () => redirect(config('app.terms_url')))->name('terms');

Route::get('source-code', fn () => redirect('https://github.com/openadministration/stumv'))->name('source-code');

require __DIR__.'/auth.php';
