<?php // routes/breadcrumbs.php

// Note: Laravel will automatically resolve `Breadcrumbs::` without
// this import. This is nice for IDE syntax and refactoring.
use App\Models\Realm;
use App\Models\Committee;
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

// Home > Blog > [Category]
Breadcrumbs::for('realms:detail', function (BreadcrumbTrail $trail, Realm $realm) {
    $trail->parent('realms:index');
    $trail->push($realm->long_name, route('realms', $realm->uid));
});

Breadcrumbs::for('groups:index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push(__('Groups'), route('groups'));
});

Breadcrumbs::for('committees:index', function (BreadcrumbTrail $trail) {
    $trail->parent('dashboard');
    $trail->push(__('Committees'), route('committees'));
});

Breadcrumbs::for('committees:detail', function (BreadcrumbTrail $trail, Committee $committee) {
    $trail->parent('committees:index');
    $trail->push($committee->name . " (" . $committee->realm_uid . ")", route('committees.detail', $committee->id));
});
