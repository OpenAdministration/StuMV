<?php

// routes/breadcrumbs.php
// Note: Laravel will automatically resolve `Breadcrumbs::` without
// this import. This is nice for IDE syntax and refactoring.
use App\Ldap\Committee;
use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
// This import is also not required, and you could replace `BreadcrumbTrail $trail`
//  with `$trail`. This is nice for IDE type checking and completion.
use Illuminate\Support\Facades\Route;

Breadcrumbs::for('realms.pick', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->push(__('realms.pick_breadcrumb'), null, ['truncate' => true]);
});

Breadcrumbs::for('realms.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->push(__('realms.new_breadcrumb'), route('realms.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms', function (BreadcrumbTrail $trail, array $routeParams): void {
    $community = Route::current()->parameter('realm');
    $name = $community->getFirstAttribute('description') ?: $community->getFirstAttribute('ou');
    $trail->push($name, route('realms.dashboard', $community->getFirstAttribute('ou')), ['truncate' => true]);
});

Breadcrumbs::for('realms.dashboard', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
});

Breadcrumbs::for('profile', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->push(__('profile.breadcrumb'), route('profile', array_merge(['username' => auth()->user()->username], $routeParams)), ['truncate' => true]);
});

Breadcrumbs::for('profile.memberships', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('profile', $routeParams);
    $trail->push(__('profile.memberships'), route('profile.memberships', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('profile.picture', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('profile', $routeParams);
    $trail->push(__('profile.picture'), route('profile.picture', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('password.change', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('profile', $routeParams);
    $trail->push(__('profile.change_password_title'), route('password.change', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('pick-realm', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->push('Wähle Realm', route('pick-realm' /* no route params! */), ['truncate' => true]);
});

Breadcrumbs::for('realms.edit', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push('Editieren', route('realms.edit', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.members', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push(__('realms.members_breadcrumb'), route('realms.members', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.members.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.members', $routeParams);
    $trail->push(__('common.new'), route('realms.members.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.mods', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push(__('realms.mods_breadcrumb'), route('realms.mods', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.mods.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.mods', $routeParams);
    $trail->push(__('common.new'), route('realms.mods.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.admins', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push(__('Admins'), route('realms.admins', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.admins.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.admins', $routeParams);
    $trail->push(__('common.new'), route('realms.admins.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.domains', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push(__('Domains'), route('realms.domains', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.domains.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.domains', $routeParams);
    $trail->push(__('domain.new_button'), route('realms.domains.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.groups', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push(__('groups.breadcrumb_title'), route('realms.groups', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.api-clients', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push(__('api_clients.list_title'), route('realms.api-clients', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.api-clients.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.api-clients', $routeParams);
    $trail->push(__('api_clients.new'), route('realms.api-clients.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.groups.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.groups', $routeParams);
    $trail->push(__('groups.new_button'), route('realms.groups.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.groups.edit', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.groups', $routeParams);
    $trail->push(__('common.edit'), route('realms.groups.edit', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.groups.roles', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.groups', $routeParams);
    $name = $routeParams['cn'];
    $trail->push($name, route('realms.groups.roles', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.groups.roles.add', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.groups.roles', $routeParams);
    $trail->push(__('Add'), route('realms.groups.roles.add', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('realms.groups.members', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms.groups', $routeParams);
    $name = $routeParams['cn'];
    $trail->push($name, route('realms.groups.members', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.list', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push(__('committees.breadcrumb_title'), route('committees.list', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.list', $routeParams);
    $trail->push(__('committees.new_button'), route('committees.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.details', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.list', $routeParams);
    $uid = $routeParams['realm'];
    $c = Committee::findByOrFail('ou', $routeParams['ou']);
    foreach ($c->committeePath() as $committee) {
        $routeParams['ou'] = $committee;
        // Display the committee's long name (description), falling back to
        // its short ou - the `truncate` flag lets the view ellipsize it via
        // CSS (~20 chars) while keeping the full name available on hover.
        $fullName = Committee::findByName($uid, $committee)?->getFirstAttribute('description');
        $trail->push($fullName ?: $committee, route('committees.roles', $routeParams), ['truncate' => true]);
    }
});

Breadcrumbs::for('committees.edit', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.details', $routeParams);
    $trail->push(__('common.edit'), route('committees.edit', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.roles', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.details', $routeParams);
});

Breadcrumbs::for('committees.roles.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.roles', $routeParams);
    $trail->push(__('roles.new_button'), route('committees.roles.new', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.roles.members', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.roles', $routeParams);
    $committee = Committee::findByName($routeParams['realm'], $routeParams['ou']);
    $role = $committee?->roles()->where('cn', $routeParams['cn'])->first();
    $name = $role?->getFirstAttribute('description') ?: $routeParams['cn'];
    $trail->push($name, route('committees.roles.members', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.roles.edit', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.roles.members', $routeParams);
    $trail->push(__('common.edit'), route('committees.roles.edit', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.roles.add-member', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.roles.members', $routeParams);
    $trail->push(__('roles.new_membership_breadcrumb'), route('committees.roles.add-member', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.roles.members.edit', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.roles.members', $routeParams);
    $trail->push(__('Edit Membership'), route('committees.roles.members.edit', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('committees.roles.terminate-memberships', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('committees.roles.members', $routeParams);
    $trail->push(__('roles.members.terminate_memberships'), route('committees.roles.terminate-memberships', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('tools.dashboard', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('realms', $routeParams);
    $trail->push(__('tools.tools'), route('tools.dashboard', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('tools.compare-email-list', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('tools.dashboard', $routeParams);
    $trail->push(__('tools.compare_email_list_headline'), route('tools.compare-email-list', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('tools.import-user-uni-ldap', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('tools.dashboard', $routeParams);
    $trail->push(__('tools.import_users_from_uni_ldap_headline'), route('tools.import-user-uni-ldap', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('tools.users-not-in-uni-ldap', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('tools.dashboard', $routeParams);
    $trail->push(__('tools.users_not_in_uni_ldap_headline'), route('tools.users-not-in-uni-ldap', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('tools.unused-roles', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('tools.dashboard', $routeParams);
    $trail->push(__('tools.unused_roles_headline'), route('tools.unused-roles', $routeParams), ['truncate' => true]);
});

Breadcrumbs::for('superadmins.list', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->push(__('Superusers'), route('superadmins.list' /* none */), ['truncate' => true]);
});

Breadcrumbs::for('superadmins.add', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->push(__('common.new'), route('superadmins.add' /* none */), ['truncate' => true]);
});

Breadcrumbs::for('oidc-clients.list', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->push(__('oidc_clients.list_title'), route('oidc-clients.list' /* none */), ['truncate' => true]);
});

Breadcrumbs::for('oidc-clients.new', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('oidc-clients.list');
    $trail->push(__('oidc_clients.new'), route('oidc-clients.new' /* none */), ['truncate' => true]);
});

Breadcrumbs::for('oidc-clients.edit', function (BreadcrumbTrail $trail, array $routeParams): void {
    $trail->parent('oidc-clients.list');
    $trail->push(__('common.edit'), route('oidc-clients.edit', $routeParams), ['truncate' => true]);
});
