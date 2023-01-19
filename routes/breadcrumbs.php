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

// Home > Blog
Breadcrumbs::for('realms:index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push('Realms', route('realms'));
});

Breadcrumbs::for('realms:members', function (BreadcrumbTrail $trail, Realm $realm) {
    $trail->parent('realms:index');
    $trail->push('Realm ' . $realm->long_name . ' (' . $realm->uid . '): ' . __('Members'), route('realms.members', $realm->uid));
});

Breadcrumbs::for('realms:admins', function (BreadcrumbTrail $trail, Realm $realm) {
    $trail->parent('realms:index');
    $trail->push('Realm ' . $realm->long_name . ' (' . $realm->uid . '): ' . __('Admins'), route('realms.members', $realm->uid));
});

Breadcrumbs::for('groups:index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push(__('Groups'), route('groups'));
});

Breadcrumbs::for('groups:roles', function (BreadcrumbTrail $trail, Group $group) {
    $trail->parent('groups:index');
    $trail->push($group->name . ' (' . $group->realm->uid . ')', route('groups.roles', $group->id));
});

Breadcrumbs::for('committees:index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push(__('Committees'), route('committees'));
});

Breadcrumbs::for('committees:roles', function (BreadcrumbTrail $trail, Committee $committee) {
    $trail->parent('committees:index');
    $trail->push($committee->name . " (" . $committee->realm_uid . ")", route('committees.roles', $committee->id));
});

Breadcrumbs::for('roles:members', function (BreadcrumbTrail $trail, Role $role) {
    $trail->parent('committees:roles', $role->committee);
    $trail->push($role->name, route('roles.members', $role->id));
});
