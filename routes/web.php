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
    Route::get('/committees', \App\Http\Livewire\Committee\Crud::class)->name('committees')
        ->can('viewAny', '\App\Models\Committee');
    Route::get('/committees/{id}', \App\Http\Livewire\Committee\Roles::class)->name('committees.roles')
        ->can('viewAny', '\App\Models\Committee');;
    Route::get('/roles/{id}', \App\Http\Livewire\Role\Members::class)->name('roles.members');
});

Route::middleware(['auth', 'superuser'])->group(function (){
    Route::get('/groups', \App\Http\Livewire\Group\Crud::class)->name('groups');
    Route::get('/groups/{id}', \App\Http\Livewire\Group\Roles::class)->name('groups.roles');
    Route::get('/realms', \App\Http\Livewire\Realm\Crud::class)->name('realms');
    Route::get('/realms/members/{uid}', \App\Http\Livewire\Realm\Members::class)->name('realms.members');
    Route::get('/realms/admins/{uid}', \App\Http\Livewire\Realm\Admins::class)->name('realms.admins');
});


require __DIR__.'/auth.php';
