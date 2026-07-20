<?php

use App\Http\Controllers\Oidc\RealmDiscoveryController;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Livewire\Api\EditApiClient;
use App\Livewire\Api\ListApiClients;
use App\Livewire\Api\NewApiClient;
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
use App\Livewire\Oidc\EditOidcClient;
use App\Livewire\Oidc\ListOidcClients;
use App\Livewire\Oidc\NewOidcClient;
use App\Livewire\Profile\Memberships;
use App\Livewire\Profile\Picture;
use App\Livewire\Profile\Profile;
use App\Livewire\Realm\CommunityDashboard;
use App\Livewire\Realm\EditRealm;
use App\Livewire\Realm\EditRealmBranding;
use App\Livewire\Realm\EditSsoProvider;
use App\Livewire\Realm\ListAdmins;
use App\Livewire\Realm\ListDomains;
use App\Livewire\Realm\ListMembers;
use App\Livewire\Realm\ListModerators;
use App\Livewire\Realm\ListRealms;
use App\Livewire\Realm\ListSsoProviders;
use App\Livewire\Realm\NewAdmin;
use App\Livewire\Realm\NewDomain;
use App\Livewire\Realm\NewMember;
use App\Livewire\Realm\NewModerator;
use App\Livewire\Realm\NewRealm;
use App\Livewire\Realm\NewSsoProvider;
use App\Livewire\Tools\CompareEmailList;
use App\Livewire\Tools\ImportUsersFromUniLdap;
use App\Livewire\Tools\ToolsDashboard;
use App\Livewire\Tools\UnusedRoles;
use App\Livewire\Tools\UsersNotInUniLdap;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;
use OpenIDConnect\Laravel\JwksController;
use OpenIDConnect\Laravel\UserInfoController;

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
    Route::livewire('{realm}/profile/{username}', Profile::class)->name('profile');
    Route::livewire('{realm}/profile/{username}/memberships', Memberships::class)->name('profile.memberships');
    Route::livewire('{realm}/profile/{username}/picture', Picture::class)->name('profile.picture');
    Route::livewire('/pick-realm', ListRealms::class)->name('realms.pick');

    Route::middleware(['communityMember'])->group(function (): void {
        // member
        Route::livewire('{realm}/dashboard', CommunityDashboard::class)->name('realms.dashboard');
        Route::livewire('{realm}/members', ListMembers::class)->name('realms.members');

        // The dedicated "admin" superadmin realm has no moderators/admins
        // groups or committees of its own - see Community::generateSkeleton().
        Route::middleware(['denyAdminRealm'])->group(function (): void {
            Route::livewire('{realm}/moderators', ListModerators::class)->name('realms.mods');
            Route::livewire('{realm}/admins', ListAdmins::class)->name('realms.admins');
            Route::livewire('{realm}/committees', ListCommitteesTree::class)->name('committees.list');
            Route::livewire('{realm}/committees/{ou}', ListRoles::class)->name('committees.roles');
            Route::livewire('{realm}/committees/{ou}/roles/{cn}', ListRoleMembers::class)->name('committees.roles.members');
            Route::livewire('{realm}/committees/{ou}/moderators', ListCommitteeModerators::class)->name('committees.moderators');
        });
        // end member
    });

    // committee/community moderators only - role and role-membership actions
    // within a committee (or one moderated by an ancestor of it)
    Route::middleware(['communityMod', 'denyAdminRealm'])->group(function (): void {
        // mod
        Route::livewire('{realm}/committees/{ou}/new-role', NewRole::class)->name('committees.roles.new');
        Route::livewire('{realm}/committees/{ou}/roles/{cn}/edit', EditRole::class)->name('committees.roles.edit');
        Route::livewire('{realm}/committees/{ou}/roles/{cn}/new-member', AddUserToRole::class)->name('committees.roles.add-member');
        Route::livewire('{realm}/committees/{ou}/roles/{cn}/terminate-memberships', TerminateRoleMemberships::class)->name('committees.roles.terminate-memberships');
        Route::livewire('{realm}/committees/{ou}/roles/{cn}/memberships/{id}', EditRoleMembership::class)->name('committees.roles.members.edit');
        Route::livewire('{realm}/committees/{ou}/new-moderator', NewCommitteeModerator::class)->name('committees.moderators.new');
        // end mod
    });

    // community moderators only - committees themselves are not delegable to
    // committee moderators, unlike their roles/role-memberships above
    Route::livewire('{realm}/new-committee', NewCommittee::class)->name('committees.new')
        ->middleware('denyAdminRealm')
        ->can('moderator', 'realm');
    Route::livewire('{realm}/committees/{ou}/edit', EditCommittee::class)->name('committees.edit')
        ->middleware('denyAdminRealm')
        ->can('moderator', 'realm');

    // mods, admins and superadmins - none of these tools are meaningful for
    // the admin realm either (no domains/committees/real members to compare
    // or sync against)
    Route::middleware(['can:tools,realm', 'denyAdminRealm'])->group(function (): void {
        Route::livewire('{realm}/tools', ToolsDashboard::class)->name('tools.dashboard');
        Route::livewire('{realm}/tools/compare-email-list', CompareEmailList::class)->name('tools.compare-email-list');
        Route::livewire('{realm}/tools/import-user-uni-ldap', ImportUsersFromUniLdap::class)->name('tools.import-user-uni-ldap');
        Route::livewire('{realm}/tools/users-not-in-uni-ldap', UsersNotInUniLdap::class)->name('tools.users-not-in-uni-ldap');
        Route::livewire('{realm}/tools/unused-roles', UnusedRoles::class)->name('tools.unused-roles');
    });

    Route::middleware(['communityAdmin'])->group(function (): void {
        // admin - none of Groups/Domains/API-clients apply to the admin
        // realm (see Community::generateSkeleton()), but editing the realm's
        // own description does.
        Route::middleware(['denyAdminRealm'])->group(function (): void {
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
            Route::livewire('{realm}/api-clients/{client}/edit', EditApiClient::class)->name('realms.api-clients.edit');
            Route::livewire('{realm}/oidc-clients', ListOidcClients::class)->name('realms.oidc-clients');
            Route::livewire('{realm}/new-oidc-client', NewOidcClient::class)->name('realms.oidc-clients.new');
            Route::livewire('{realm}/oidc-clients/{client}/edit', EditOidcClient::class)->name('realms.oidc-clients.edit');
            Route::livewire('{realm}/sso-providers', ListSsoProviders::class)->name('realms.sso-providers');
            Route::livewire('{realm}/new-sso-provider', NewSsoProvider::class)->name('realms.sso-providers.new');
            Route::livewire('{realm}/sso-providers/{provider}/edit', EditSsoProvider::class)->name('realms.sso-providers.edit');
        });
        Route::livewire('{realm}/edit', EditRealm::class)->name('realms.edit');
        Route::livewire('{realm}/branding', EditRealmBranding::class)->name('realms.branding');
        // end admin
    });

    // fine grained permissions
    Route::livewire('{realm}/new-moderator', NewModerator::class)->name('realms.mods.new')
        ->can('add_moderator', 'realm');

    Route::middleware([SuperAdminMiddleware::class])->group(function (): void {
        Route::livewire('{realm}/new-member', NewMember::class)->name('realms.members.new');
        Route::livewire('new-realm', NewRealm::class)->name('realms.new');
    });
    // end auth verified
});

// Realm-bound OIDC/OAuth2 protocol endpoints. Replaces Passport's own global
// /oauth/* routes (disabled via Passport::ignoreRoutes() in
// AppServiceProvider) and the OpenID Connect package's global
// discovery/jwks/userinfo routes (disabled via config/openid.php) - only
// accounts authenticating through a client's own bound realm may use it,
// see EnsureOidcClientMatchesRealm. Not nested in the auth+verified group
// above: these must stay reachable by guests (the token endpoint, the
// initial authorize redirect-to-login, discovery, jwks).
Route::group([
    'as' => 'realm.passport.',
    'prefix' => '{realm}/oauth',
    'namespace' => 'Laravel\Passport\Http\Controllers',
    'middleware' => config('passport.middleware', []),
], function (): void {
    require base_path('vendor/laravel/passport/routes/web.php');
});
Route::getRoutes()->getByName('realm.passport.authorizations.authorize')?->middleware('oidcClientMatchesRealm');
Route::getRoutes()->getByName('realm.passport.token')?->middleware('oidcClientMatchesRealm');

Route::get('{realm}/oauth/jwks', JwksController::class)->name('realm.openid.jwks');
Route::get('{realm}/oauth/userinfo', UserInfoController::class)->middleware('auth:api')->name('realm.openid.userinfo');
Route::get('{realm}/.well-known/openid-configuration', RealmDiscoveryController::class)->name('realm.openid.discovery');

// guest routes
Route::get('about', fn () => redirect(config('app.about_url')))->name('about');
Route::get('privacy', fn () => redirect(config('app.privacy_url')))->name('privacy');
Route::get('terms', fn () => redirect(config('app.terms_url')))->name('terms');

Route::get('documentation', fn () => redirect('https://www.stufis.de/stumv'))->name('documentation');
Route::get('source-code', fn () => redirect('https://github.com/openadministration/stumv'))->name('source-code');

require __DIR__.'/auth.php';
