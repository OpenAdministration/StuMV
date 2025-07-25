<?php // routes/breadcrumbs.php

// Note: Laravel will automatically resolve `Breadcrumbs::` without
// this import. This is nice for IDE syntax and refactoring.
use App\Ldap\Committee;
use Diglactic\Breadcrumbs\Breadcrumbs;

// This import is also not required, and you could replace `BreadcrumbTrail $trail`
//  with `$trail`. This is nice for IDE type checking and completion.
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use LdapRecord\Models\Attributes\DistinguishedNameBuilder;

Breadcrumbs::for('realms.pick', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->push(__('Enter a Realm'));
});

Breadcrumbs::for('realms.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->push(__('Add new Realm'), route('realms.new', $routeParams));
});

Breadcrumbs::for('realms', function (BreadcrumbTrail $trail, array $routeParams) {
    $community = \Illuminate\Support\Facades\Route::current()->parameter('uid');
    $trail->push($community->getFirstAttribute('ou'), route('realms.dashboard', $community->getFirstAttribute('ou')));
});

Breadcrumbs::for('realms.dashboard', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms', $routeParams);
    $trail->push(__('Dashboard'), route('realms.dashboard', $routeParams));
});

Breadcrumbs::for('profile', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->push(__('Profile'), route('profile', $routeParams));
});

Breadcrumbs::for('profile.memberships', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('profile', $routeParams);
    $trail->push(__('profile.memberships'), route('profile.memberships', $routeParams));
});

Breadcrumbs::for('password.change', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('profile', $routeParams);
    $trail->push(__('Change Password'), route('password.change', $routeParams));
});

Breadcrumbs::for('pick-realm', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->push('Wähle Realm', route('pick-realm' /* no route params! */));
});

Breadcrumbs::for('realms.edit', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms', $routeParams);
    $trail->push('Editieren', route('realms.edit', $routeParams));
});


Breadcrumbs::for('realms.members', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms', $routeParams);
    $trail->push(__('Members'), route('realms.members', $routeParams));
});

Breadcrumbs::for('realms.members.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.members', $routeParams);
    $trail->push(__('New'), route('realms.members.new', $routeParams));
});

Breadcrumbs::for('realms.mods', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms', $routeParams);
    $trail->push(__('Moderators'), route('realms.mods', $routeParams));
});

Breadcrumbs::for('realms.mods.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.mods', $routeParams);
    $trail->push(__('New'), route('realms.mods.new', $routeParams));
});

Breadcrumbs::for('realms.admins', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms', $routeParams);
    $trail->push(__('Admins'), route('realms.admins', $routeParams));
});

Breadcrumbs::for('realms.admins.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.admins', $routeParams);
    $trail->push(__('New'), route('realms.admins.new', $routeParams));
});

Breadcrumbs::for('realms.domains', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms', $routeParams);
    $trail->push(__('Domains'), route('realms.domains', $routeParams));
});

Breadcrumbs::for('realms.domains.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.domains', $routeParams);
    $trail->push(__('New Domain'), route('realms.domains.new', $routeParams));
});

Breadcrumbs::for('realms.groups', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms', $routeParams);
    $trail->push(__('Groups'), route('realms.groups', $routeParams));
});

Breadcrumbs::for('realms.groups.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.groups', $routeParams);
    $trail->push(__('New Group'), route('realms.groups.new', $routeParams));
});

Breadcrumbs::for('realms.groups.edit', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.groups', $routeParams);
    $trail->push(__('Edit'), route('realms.groups.edit', $routeParams));
});

Breadcrumbs::for('realms.groups.roles', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.groups', $routeParams);
    $name = $routeParams['cn'];
    $trail->push( $name, route('realms.groups.roles', $routeParams));
});

Breadcrumbs::for('realms.groups.roles.add', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.groups.roles', $routeParams);
    $trail->push( __('Add'), route('realms.groups.roles.add', $routeParams));
});

Breadcrumbs::for('committees.list', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms',  $routeParams);
    $trail->push(__('Committees'), route('committees.list', $routeParams));
});

Breadcrumbs::for('committees.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('committees.list',  $routeParams);
    $trail->push(__('New Committee'), route('committees.new', $routeParams));
});

Breadcrumbs::for('committees.details', function (BreadcrumbTrail $trail, array $routeParams){
    $trail->parent('committees.list', $routeParams);
    $c = Committee::findByOrFail('ou', $routeParams['ou']);
    foreach ($c->committeePath() as $committee){
        $routeParams['ou'] = $committee;
        $trail->push($committee, route('committees.roles', $routeParams));
    }
});

Breadcrumbs::for('committees.edit', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('committees.details', $routeParams);
    $trail->push(__('Edit'), route('committees.edit', $routeParams));
});

Breadcrumbs::for('committees.roles', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('committees.details', $routeParams);
    $trail->push(__('Roles'), route('committees.roles', $routeParams));
});

Breadcrumbs::for('committees.roles.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('committees.roles', $routeParams);
    $trail->push(__('New'), route('committees.roles.new', $routeParams));
});

Breadcrumbs::for('committees.roles.members', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('committees.roles', $routeParams);
    $trail->push($routeParams['cn'], route('committees.roles.members', $routeParams));
});

Breadcrumbs::for('committees.roles.edit', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('committees.roles.members', $routeParams);
    $trail->push(__('Edit'), route('committees.roles.edit', $routeParams));
});

Breadcrumbs::for('committees.roles.add-member', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('committees.roles.members', $routeParams);
    $trail->push(__('New Membership'), route('committees.roles.add-member', $routeParams));
});

Breadcrumbs::for('committees.roles.members.edit', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('committees.roles.members', $routeParams);
    $trail->push(__('Edit Membership'), route('committees.roles.members.edit', $routeParams));
});

Breadcrumbs::for('superadmins.list', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->push(__('Superusers'), route('superadmins.list' /* none */));
});

Breadcrumbs::for('superadmins.add', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->push(__('New'), route('superadmins.add' /* none */));
});
