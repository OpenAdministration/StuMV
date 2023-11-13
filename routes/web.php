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
    Route::get('/profile', \App\Livewire\Profile::class)->name('profile');
    Route::get('/pick-realm', \App\Livewire\Realm\ListRealms::class)->name('realms.pick');

    // super user only
    Route::get('/new-realm', \App\Livewire\Realm\NewRealm::class)->name('realms.new');
    Route::get('/{uid}/edit', \App\Livewire\Realm\EditRealm::class)->name('realms.edit');
});

Route::middleware([\App\Http\Middleware\ActiveRealm::class, 'auth'])->group(function (){
    Route::get('/{uid}/members/', \App\Livewire\Realm\Members::class)->name('realms.members');
    Route::get('/{uid}/new-member', \App\Livewire\Realm\NewMember::class)->name('realms.members.new');
    Route::get('/{uid}/mods/', \App\Livewire\Realm\Moderators::class)->name('realms.mods');
    Route::get('/{uid}/new-mod', \App\Livewire\Realm\NewModerator::class)->name('realms.mods.new');
    Route::get('/{uid}/admins/', \App\Livewire\Realm\Admins::class)->name('realms.admins');
    Route::get('/{uid}/new-admin', \App\Livewire\Realm\NewAdmin::class)->name('realms.admins.new');
    Route::get('/{uid}/groups', \App\Livewire\Group\ListGroups::class)->name('realms.groups');
    Route::get('/{uid}/new-group', \App\Livewire\Group\NewGroup::class)->name('realms.groups.new');
    Route::get('/{uid}/group/{cn}/roles', \App\Livewire\Group\Roles::class)->name('realms.groups.roles');
    Route::get('/{uid}/group/{cn}/add-role', \App\Livewire\Group\Roles::class)->name('realms.groups.roles.add');
    Route::get('/{uid}/committees', \App\Livewire\Committee\ListCommittees::class)->name('committees.list');
    Route::get('/{uid}/new-committee', \App\Livewire\Committee\NewCommittee::class)->name('committees.new');
    Route::get('/{uid}/committees/{ou}', \App\Livewire\Committee\ListRoles::class)->name('committees.roles');
    Route::get('/{uid}/committees/{ou}/new-role', \App\Livewire\Committee\NewRole::class)->name('committees.roles.new');
    Route::get('/{uid}/committees/{ou}/role/{cn}', \App\Livewire\Committee\AddUserToRole::class)->name('committees.roles.members');
});


require __DIR__.'/auth.php';
