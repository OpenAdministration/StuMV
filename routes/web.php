<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function (){
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::get('/realms', \App\Http\Livewire\Realm\Crud::class)->name('realms');
    Route::get('/groups', \App\Http\Livewire\Group\Crud::class)->name('groups');
    Route::get('/committees', \App\Http\Livewire\Committee\Crud::class)->name('committees');
    Route::get('/committees/{id}', \App\Http\Livewire\Committee\Roles::class)->name('committees.roles');
    Route::get('/roles/{id}', \App\Http\Livewire\Role\Members::class)->name('roles.members');
});


require __DIR__.'/auth.php';
