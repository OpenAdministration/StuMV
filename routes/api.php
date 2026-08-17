<?php

use App\Http\Controllers\Api\Directory\Committees;
use App\Http\Controllers\Api\Directory\Groups;
use App\Http\Controllers\Api\Directory\Users;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
Route::middleware('client')->group(function (): void {
    Route::middleware('scope:committees')->group(function (): void {
        Route::get('{realm}/committees', [Committees::class, 'index']);
        Route::get('{realm}/committees/{ou}', [Committees::class, 'show']);
        Route::get('{realm}/committees/{ou}/roles', [Committees::class, 'roles']);
        Route::get('{realm}/committees/{ou}/roles/{cn}', [Committees::class, 'role']);
        Route::get('{realm}/committees/{ou}/roles/{cn}/members', [Committees::class, 'roleMembers']);
        Route::get('{realm}/members', [Committees::class, 'rolesMembers']);
    });

    Route::middleware('scope:groups')->group(function (): void {
        Route::get('{realm}/groups', [Groups::class, 'index']);
        Route::get('{realm}/groups/{cn}', [Groups::class, 'show']);
        Route::get('{realm}/groups/{cn}/members', [Groups::class, 'members']);
    });

    Route::middleware('scope:users')->group(function (): void {
        Route::get('{realm}/users/{uid}', [Users::class, 'show']);
        Route::get('{realm}/users/{uid}/roles', [Users::class, 'roles']);
        Route::get('{realm}/users/{uid}/committees', [Users::class, 'committees']);
        Route::get('{realm}/users/{uid}/groups', [Users::class, 'groups']);
    });
});
