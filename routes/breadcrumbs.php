<?php // routes/breadcrumbs.php

// Note: Laravel will automatically resolve `Breadcrumbs::` without
// this import. This is nice for IDE syntax and refactoring.
use App\Models\Group;
use App\Models\Realm;
use App\Models\Committee;
use App\Models\Role;
use Diglactic\Breadcrumbs\Breadcrumbs;

// This import is also not required, and you could replace `BreadcrumbTrail $trail`
//  with `$trail`. This is nice for IDE type checking and completion.
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;

// Home
Breadcrumbs::for('dashboard', function (BreadcrumbTrail $trail) {
    $trail->push('Home', route('dashboard'));
});

Breadcrumbs::for('profile', function (BreadcrumbTrail $trail) {
    $trail->push(__('Profile'), route('profile'));
});

Breadcrumbs::for('password.change', function (BreadcrumbTrail $trail) {
    $trail->parent('profile');
    $trail->push(__('Change password'), route('password.change'));
});

// Home > Blog
Breadcrumbs::for('realms', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Realms', route('realms'));
});

Breadcrumbs::for('realms.new', function (BreadcrumbTrail $trail) {
    $trail->parent('realms');
    $trail->push('Neu', route('realms.new'));
});

Breadcrumbs::for('realms.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('realms');
    $trail->push('Editieren', route('realms.edit'));
});

Breadcrumbs::for('realms.view', function (BreadcrumbTrail $trail, array $routeParams){
    $trail->parent('realms', $routeParams);
    $uid = $routeParams['uid'];
    $community = \App\Ldap\Community::findOrFailByUid($uid);
    $trail->push($community->description[0] . ' (' . $community->ou[0] . ')');//, route('realms.view', $uid)); // not yet defined
});

Breadcrumbs::for('realms.members', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.view', $routeParams);
    $trail->push(__('Members'), route('realms.members', $routeParams));
});

Breadcrumbs::for('realms.members.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.members', $routeParams);
    $trail->push(__('New'), route('realms.members.new', $routeParams));
});

Breadcrumbs::for('realms.mods', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.view', $routeParams);
    $trail->push(__('Moderators'), route('realms.mods', $routeParams));
});

Breadcrumbs::for('realms.mods.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.mods', $routeParams);
    $trail->push(__('New'), route('realms.mods.new', $routeParams));
});

Breadcrumbs::for('realms.admins', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.view', $routeParams);
    $trail->push(__('Admins'), route('realms.admins', $routeParams));
});

Breadcrumbs::for('realms.admins.new', function (BreadcrumbTrail $trail, array $routeParams) {
    $trail->parent('realms.admins', $routeParams);
    $trail->push(__('New'), route('realms.admins.new', $routeParams));
});

Breadcrumbs::for('groups:index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push(__('Groups'), route('groups'));
});

Breadcrumbs::for('groups:roles', function (BreadcrumbTrail $trail, Group $group) {
    $trail->parent('groups:index');
    $trail->push($group->name . ' (' . $group->realm->uid . ')', route('groups.roles', $group->id));
});

Breadcrumbs::for('committees.list', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push(__('Committees'), route('committees.list'));
});

Breadcrumbs::for('committees.roles', function (BreadcrumbTrail $trail, Committee $committee) {
    $trail->parent('committees.list');
    $trail->push($committee->name . " (" . $committee->realm_uid . ")", route('committees.roles', $committee->id));
});

Breadcrumbs::for('roles:members', function (BreadcrumbTrail $trail, Role $role) {
    $trail->parent('committees.roles', $role->committee);
    $trail->push($role->name, route('roles.members', $role->id));
});
