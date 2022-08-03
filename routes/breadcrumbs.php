<?php // routes/breadcrumbs.php

// Note: Laravel will automatically resolve `Breadcrumbs::` without
// this import. This is nice for IDE syntax and refactoring.
use App\Models\Realm;
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
Breadcrumbs::for('realms:details', function (BreadcrumbTrail $trail, Realm $realm) {
    $trail->parent('realms');
    $trail->push($realm->long_name, route('realms', $realm->uid));
});
