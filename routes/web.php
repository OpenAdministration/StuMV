<?php

use App\Http\Controllers\Oidc\EndSessionController;
use App\Http\Controllers\Oidc\IntrospectionController;
use App\Http\Controllers\Oidc\RealmDiscoveryController;
use App\Http\Controllers\Oidc\RevocationController;
use App\Http\Controllers\Oidc\UserInfoController;
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
use App\Livewire\IdentityProvider\EditIdentityProvider;
use App\Livewire\IdentityProvider\ListIdentityProviders;
use App\Livewire\IdentityProvider\NewIdentityProvider;
use App\Livewire\Mailman\ListGroupMailmanLists;
use App\Livewire\Mailman\ListMailmanListMembers;
use App\Livewire\Mailman\NewGroupMailmanList;
use App\Livewire\Oidc\EditOidcClient;
use App\Livewire\Oidc\ListOidcClients;
use App\Livewire\Oidc\NewOidcClient;
use App\Livewire\Profile\Memberships;
use App\Livewire\Profile\Picture;
use App\Livewire\Profile\Profile;
use App\Livewire\Realm\CommunityDashboard;
use App\Livewire\Realm\EditRealm;
use App\Livewire\Realm\EditRealmBranding;
use App\Livewire\Realm\ListAdmins;
use App\Livewire\Realm\ListDomains;
use App\Livewire\Realm\ListMembers;
use App\Livewire\Realm\ListModerators;
use App\Livewire\Realm\ListRealms;
use App\Livewire\Realm\NewAdmin;
use App\Livewire\Realm\NewDomain;
use App\Livewire\Realm\NewMember;
use App\Livewire\Realm\NewModerator;
use App\Livewire\Realm\NewRealm;
use App\Livewire\Tools\CompareEmailList;
use App\Livewire\Tools\ImportUsersFromUniLdap;
use App\Livewire\Tools\InviteUser;
use App\Livewire\Tools\ListInvitations;
use App\Livewire\Tools\ToolsDashboard;
use App\Livewire\Tools\UnusedRoles;
use App\Livewire\Tools\UsersNotInUniLdap;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;
use OpenIDConnect\Laravel\JwksController;

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
        Route::livewire('{realm}/tools/invite-user', InviteUser::class)->name('tools.invite-user');
        Route::livewire('{realm}/tools/invitations', ListInvitations::class)->name('tools.invitations');
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
            Route::livewire('{realm}/group-mailman-lists', ListGroupMailmanLists::class)->name('realms.group-mailman-lists');
            Route::livewire('{realm}/new-group-mailman-list', NewGroupMailmanList::class)->name('realms.group-mailman-lists.new');
            Route::livewire('{realm}/group-mailman-lists/{listId}/members', ListMailmanListMembers::class)->name('realms.group-mailman-lists.members');
            Route::livewire('{realm}/domains', ListDomains::class)->name('realms.domains');
            Route::livewire('{realm}/new-domain', NewDomain::class)->name('realms.domains.new');
            Route::livewire('{realm}/api-clients', ListApiClients::class)->name('realms.api-clients');
            Route::livewire('{realm}/new-api-client', NewApiClient::class)->name('realms.api-clients.new');
            Route::livewire('{realm}/api-clients/{client}/edit', EditApiClient::class)->name('realms.api-clients.edit');
            Route::livewire('{realm}/oidc-clients', ListOidcClients::class)->name('realms.oidc-clients');
            Route::livewire('{realm}/new-oidc-client', NewOidcClient::class)->name('realms.oidc-clients.new');
            Route::livewire('{realm}/oidc-clients/{client}/edit', EditOidcClient::class)->name('realms.oidc-clients.edit');
            Route::livewire('{realm}/identity-providers', ListIdentityProviders::class)->name('realms.identity-providers');
            Route::livewire('{realm}/new-identity-provider', NewIdentityProvider::class)->name('realms.identity-providers.new');
            Route::livewire('{realm}/identity-providers/{provider}/edit', EditIdentityProvider::class)->name('realms.identity-providers.edit');
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
Route::getRoutes()->getByName('realm.passport.authorizations.authorize')?->middleware(['oidcClientMatchesRealm', 'enforceMaxAge', 'stashOidcNonce']);
Route::getRoutes()->getByName('realm.passport.token')?->middleware('oidcClientMatchesRealm');
Route::getRoutes()->getByName('realm.passport.authorizations.approve')?->middleware('logOidcConsentDecision');
Route::getRoutes()->getByName('realm.passport.authorizations.deny')?->middleware('logOidcConsentDecision');

Route::get('{realm}/oauth/jwks', JwksController::class)->name('realm.openid.jwks');
Route::get('{realm}/oauth/userinfo', UserInfoController::class)->middleware('auth:api')->name('realm.openid.userinfo');
Route::get('{realm}/.well-known/openid-configuration', RealmDiscoveryController::class)->name('realm.openid.discovery');
// RP-Initiated Logout 1.0 - see App\Http\Controllers\Oidc\EndSessionController.
// GET and POST: the spec allows either (some clients redirect via a form
// POST instead of a GET navigation).
Route::match(['get', 'post'], '{realm}/oauth/end-session', EndSessionController::class)->name('realm.openid.end_session');
// RFC 7662 Token Introspection - see App\Http\Controllers\Oidc\IntrospectionController.
// Client-authenticated like the token endpoint, so it needs the same realm
// check (a client from realm A must not be able to introspect - or even
// learn anything about - a token by posing as realm B). Middleware is
// chained directly (unlike the authorize/token routes above, which are
// required in from Passport's own routes/web.php and can only be reached
// afterward via Route::getRoutes()->getByName()) since this route is
// defined right here. Throttled per client_id (see RouteServiceProvider's
// "oidc-client" limiter) - without it this endpoint would be an unlimited
// oracle for guessing a client_secret or probing whether a token is valid.
Route::post('{realm}/oauth/introspect', IntrospectionController::class)
    ->middleware(['oidcClientMatchesRealm', 'throttle:oidc-client'])
    ->name('realm.openid.introspection');
// RFC 7009 Token Revocation - see App\Http\Controllers\Oidc\RevocationController.
// Same client-auth/realm/throttling story as introspection above.
Route::post('{realm}/oauth/revoke', RevocationController::class)
    ->middleware(['oidcClientMatchesRealm', 'throttle:oidc-client'])
    ->name('realm.openid.revocation');

// guest routes
Route::get('imprint', fn () => redirect(config('app.imprint_url')))->name('imprint');
Route::get('privacy', fn () => redirect(config('app.privacy_url')))->name('privacy');
Route::get('terms', fn () => redirect(config('app.terms_url')))->name('terms');

Route::get('documentation', fn () => redirect('https://www.stufis.de/stumv'))->name('documentation');
Route::get('source-code', fn () => redirect('https://github.com/openadministration/stumv'))->name('source-code');

require __DIR__.'/auth.php';
Route::get('_debug-navmenu', fn () => view('_debug_navmenu'));

// A realm-scoped fallback. Existing only so the {realm} segment resolves to
// a real Community for URLs like "{realm}/memberss" (a typo, not a bad
// realm slug) - that gives the 404 page's <x-navigation>/breadcrumbs (which
// key off Route::current()'s own "realm" parameter, not the visitor's
// account) the same realm context a real {realm}/... route would have.
//
// No "auth"/"verified" middleware here on purpose: auth()->check() already
// reflects the real session regardless (the "web" group still runs), and
// requiring auth would turn a guest hitting this into a redirect/401
// instead of the plain 404 every other genuinely-unmatched URL gets -
// which is exactly what broke a non-realm global path like
// ".well-known/openid-configuration" (2 segments, structurally matches
// {realm}/{any} even though it isn't realm-prefixed at all) into a 401.
//
// Marked ->fallback() rather than registered as an ordinary route:
// RouteCollection::matchAgainstRoutes() then tries it only once nothing
// registered later matches either, so it can no longer shadow a route
// registered afterwards - e.g. an ad-hoc route a test registers at runtime
// for its own URL under {realm}/... - and no longer needs to be the literal
// last route in this file.
//
// Registered BEFORE the bare fallback() below: when two fallback routes
// both match (any {realm}/... 2+ segment path also matches the bare
// wildcard), matchAgainstRoutes() keeps only the FIRST fallback it sees
// (`$fallbackRoute ??= $route`) - this one needs to win so Route::current()
// carries the "realm" parameter and the "realms.fallback" name (for
// breadcrumbs) instead of the bare fallback's nameless, realm-less route.
Route::get('{realm}/{any}', fn () => abort(404))
    ->where('any', '.*')
    ->fallback()
    ->name('realms.fallback');

// Without this, a URL that matches no route at all never enters the "web"
// group at all (StartSession/auth included) - the router bails out before
// any group middleware runs - so auth()->check() is always false by the
// time resources/views/errors/404.blade.php decides which layout to use,
// even for an actually-logged-in visitor. A fallback route is itself a
// real matched route in the "web" group (this file is already wrapped in
// it - see RouteServiceProvider::boot()), so session/auth work normally
// when the 404 it throws gets rendered. Only actually reached for paths
// that don't even structurally match {realm}/{any} above (e.g. a single
// path segment).
Route::fallback(fn () => abort(404));
