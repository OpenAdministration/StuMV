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

    Route::get('/roles/{id}', \App\Livewire\Role\Members::class)->name('roles.members');
    Route::get('/profile', \App\Livewire\Profile::class)->name('profile');
});

Route::middleware(['auth', 'superuser'])->group(function (){
    Route::get('/realms', \App\Livewire\Realm\ListRealms::class)->name('realms');
    Route::get('/realms/new', \App\Livewire\Realm\NewRealm::class)->name('realms.new');
    Route::get('/realm/{uid}/edit', \App\Livewire\Realm\EditRealm::class)->name('realms.edit');
    Route::get('/realm/{uid}/members/', \App\Livewire\Realm\Members::class)->name('realms.members');
    Route::get('/realm/{uid}/new-member', \App\Livewire\Realm\NewMember::class)->name('realms.members.new');
    Route::get('/realm/{uid}/mods/', \App\Livewire\Realm\Moderators::class)->name('realms.mods');
    Route::get('/realm/{uid}/new-mod', \App\Livewire\Realm\NewModerator::class)->name('realms.mods.new');
    Route::get('/realm/{uid}/admins/', \App\Livewire\Realm\Admins::class)->name('realms.admins');
    Route::get('/realm/{uid}/new-admin', \App\Livewire\Realm\NewAdmin::class)->name('realms.admins.new');

    Route::get('/realm/{uid}/groups', \App\Livewire\Group\ListGroups::class)->name('realms.groups');
    Route::get('/realm/{uid}/new-group', \App\Livewire\Group\NewGroup::class)->name('realms.groups.new');
    Route::get('/realm/{uid}/group/{cn}/roles', \App\Livewire\Group\Roles::class)->name('realms.groups.roles');
    Route::get('/realm/{uid}/group/{cn}/add-role', \App\Livewire\Group\Roles::class)->name('realms.groups.roles.add');

    Route::get('/realm/{uid}/committees', \App\Livewire\Committee\ListCommittees::class)->name('committees.list');
    Route::get('/realm/{uid}/new-committee', \App\Livewire\Committee\NewCommittee::class)->name('committees.new');
    Route::get('/realm/{uid}/committees/{id}', \App\Livewire\Committee\Roles::class)->name('committees.roles');
});


require __DIR__.'/auth.php';
