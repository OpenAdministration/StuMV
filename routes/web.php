<?php

use App\Http\Middleware\ActiveRealm;
use App\Http\Middleware\SuperAdminMiddleware;
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

Route::middleware(['auth', 'verified'])->group(function (){

    Route::get('/', static function (){
        return redirect()->route('realms.pick');
    });
    Route::get('/profile', \App\Livewire\Profile::class)->name('profile');
    Route::get('/pick-realm', \App\Livewire\Realm\ListRealms::class)->name('realms.pick');
});

Route::middleware(['picked-community', 'auth', 'verified'])->group(function (){

    Route::get('/{uid}/dashboard', static function (){
        return view('dashboard');
    })->name('dashboard');
    // member

    Route::get('/{uid}/mods/', \App\Livewire\Realm\Moderators::class)->name('realms.mods');
    Route::get('/{uid}/admins/', \App\Livewire\Realm\Admins::class)->name('realms.admins');
    Route::get('/{uid}/committees', \App\Livewire\Committee\ListCommittees::class)->name('committees.list');
    Route::get('/{uid}/committees/{ou}', \App\Livewire\Committee\ListRoles::class)->name('committees.roles');
    Route::get('/{uid}/committees/{ou}/role/{cn}', \App\Livewire\Committee\RoleMembers::class)->name('committees.roles.members');
});

Route::middleware(['communityMod', 'auth', 'verified'])->group(function (){
    // mod
    Route::get('/{uid}/new-committee', \App\Livewire\Committee\NewCommittee::class)->name('committees.new');
    Route::get('/{uid}/committees/{ou}/new-role', \App\Livewire\Committee\NewRole::class)->name('committees.roles.new');
    Route::get('/{uid}/committees/{ou}/edit', \App\Livewire\Committee\EditCommittee::class)->name('committees.edit');
    Route::get('/{uid}/committees/{ou}/role/{cn}/new-member', \App\Livewire\Committee\AddUserToRole::class)->name('committees.roles.add-member');
    Route::get('/{uid}/committees/{ou}/role/{cn}/membership/{id}', \App\Livewire\Committee\EditRoleMembership::class)->name('committees.roles.members.edit');
    Route::get('/{uid}/members/', \App\Livewire\Realm\Members::class)->name('realms.members');
    Route::get('/{uid}/new-mod', \App\Livewire\Realm\NewModerator::class)->name('realms.mods.new');
});

Route::middleware(['communityAdmin', 'auth', 'verified'])->group(function (){
    // admin
    Route::get('/{uid}/new-member', \App\Livewire\Realm\NewMember::class)->name('realms.members.new');
    Route::get('/{uid}/new-admin', \App\Livewire\Realm\NewAdmin::class)->name('realms.admins.new');
    Route::get('/{uid}/groups', \App\Livewire\Group\ListGroups::class)->name('realms.groups');
    Route::get('/{uid}/new-group', \App\Livewire\Group\NewGroup::class)->name('realms.groups.new');
    Route::get('/{uid}/group/{cn}/roles', \App\Livewire\Group\Roles::class)->name('realms.groups.roles');
    Route::get('/{uid}/group/{cn}/add-role', \App\Livewire\Group\Roles::class)->name('realms.groups.roles.add');
    Route::get('/{uid}/domains', \App\Livewire\Realm\ListDomains::class)->name('realms.domains');
    Route::get('/{uid}/new-domain', \App\Livewire\Realm\NewDomain::class)->name('realms.domains.new');
    Route::get('/{uid}/edit', \App\Livewire\Realm\EditRealm::class)->name('realms.edit');
});

Route::middleware([SuperAdminMiddleware::class, 'auth', 'verified'])->group(function (){
    Route::get('/superadmins', \App\Livewire\ListSuperUsers::class)->name('superadmins.list');
    Route::get('/add-superadmin', \App\Livewire\AddSuperUser::class)->name('superadmins.add');
    Route::get('/new-realm', \App\Livewire\Realm\NewRealm::class)->name('realms.new');
});

Route::get('/about', function (){
    return redirect(config('app.about_url'));
})->name('about');

Route::get('/privacy', function (){
    return redirect(config('app.privacy_url'));
})->name('privacy');

Route::get('/terms', function (){
    return redirect(config('app.terms_url'));
})->name('terms');

require __DIR__.'/auth.php';
