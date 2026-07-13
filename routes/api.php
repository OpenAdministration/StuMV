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
        Route::get('{uid}/committees', [Committees::class, 'index']);
        Route::get('{uid}/committees/{ou}/roles', [Committees::class, 'roles']);
        Route::get('{uid}/committees/{ou}/roles/{cn}/members', [Committees::class, 'roleMembers']);
    });

    Route::middleware('scope:groups')->group(function (): void {
        Route::get('{uid}/groups', [Groups::class, 'index']);
        Route::get('{uid}/groups/{cn}/members', [Groups::class, 'members']);
    });

    Route::middleware('scope:users')->group(function (): void {
        Route::get('{uid}/users/{username}', [Users::class, 'show']);
    });
});
