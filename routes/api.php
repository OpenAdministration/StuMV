<?php

use App\Http\Controllers\Api\Committees;
use App\Http\Controllers\Api\Groups;
use App\Http\Controllers\Api\SocialiteUser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::middleware('auth:api')->group(function (): void {
    Route::any('user', SocialiteUser::class);

    Route::middleware('scope:committees')->group(function (): void {
        Route::any('my/committees', [Committees::class, 'all']);
        Route::any('my/committees/{community_uid}', [Committees::class, 'fromCommunity']);
    });

    Route::middleware('scope:groups')->group(function (): void {
        Route::any('my/groups', [Groups::class, 'all']);
        Route::any('my/groups/{community_uid}', [Groups::class, 'fromCommunity']);
    });

});
