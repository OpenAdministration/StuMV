<?php

use App\Models\Committee;
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
    Route::get('/committees', \App\Livewire\Committee\Crud::class)->name('committees.list')
        ->can('viewAny', Committee::class);
    Route::get('/committees/{id}', \App\Livewire\Committee\Roles::class)->name('committees.roles')
        ->can('viewAny', Committee::class);
    Route::get('/roles/{id}', \App\Livewire\Role\Members::class)->name('roles.members')
        ->can('viewAny', Committee::class);
    Route::get('/profile', \App\Livewire\Profile::class)->name('profile');
});

Route::middleware(['auth', 'superuser'])->group(function (){
    Route::get('/groups', \App\Livewire\Group\Crud::class)->name('groups');
    Route::get('/groups/{id}', \App\Livewire\Group\Roles::class)->name('groups.roles');
    Route::get('/realms', \App\Livewire\Realm\ListRealms::class)->name('realms');
    Route::get('/realms/new', \App\Livewire\Realm\NewRealm::class)->name('realms.new');
    Route::get('/realms/edit', \App\Livewire\Realm\EditRealm::class)->name('realms.edit');
    Route::get('/realms/members/{uid}', \App\Livewire\Realm\Members::class)->name('realms.members');
    Route::get('/realms/members/{uid}/new', \App\Livewire\Realm\NewMember::class)->name('realms.members.new');
    Route::get('/realms/mods/{uid}', \App\Livewire\Realm\Moderators::class)->name('realms.mods');
    Route::get('/realms/mods/{uid}/new', \App\Livewire\Realm\NewModerator::class)->name('realms.mods.new');
    Route::get('/realms/admins/{uid}', \App\Livewire\Realm\Admins::class)->name('realms.admins');
    Route::get('/realms/admins/{uid}/new', \App\Livewire\Realm\NewAdmin::class)->name('realms.admins.new');
});


require __DIR__.'/auth.php';
