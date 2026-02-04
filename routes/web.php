<?php

use App\Http\Middleware\CommunityMember;
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
    Route::livewire('/profile/{username}', \App\Livewire\Profile::class)->name('profile');
    Route::livewire('/profile/{username}/memberships', \App\Livewire\Profile\Memberships::class)->name('profile.memberships');
    Route::livewire('/profile/{username}/picture', \App\Livewire\Profile\Picture::class)->name('profile.picture');
    Route::livewire('/pick-realm', \App\Livewire\Realm\ListRealms::class)->name('realms.pick');

    Route::middleware(['communityMember'])->group(function (){
        // member
        Route::livewire('{uid}/dashboard', \App\Livewire\Realm\CommunityDashboard::class)->name('realms.dashboard');
        Route::livewire('{uid}/members/', \App\Livewire\Realm\ListMembers::class)->name('realms.members');
        Route::livewire('{uid}/mods/', \App\Livewire\Realm\ListModerators::class)->name('realms.mods');
        Route::livewire('{uid}/admins/', \App\Livewire\Realm\ListAdmins::class)->name('realms.admins');
        Route::livewire('{uid}/committees', \App\Livewire\Committee\ListCommittees::class)->name('committees.list');
        Route::livewire('{uid}/committees/{ou}', \App\Livewire\Committee\ListRoles::class)->name('committees.roles');
        Route::livewire('{uid}/committees/{ou}/role/{cn}', \App\Livewire\Committee\ListRoleMembers::class)->name('committees.roles.members');
        // end member
    });

    // mods only
    Route::middleware(['communityMod'])->group(function (){
        // mod
        Route::livewire('{uid}/new-committee', \App\Livewire\Committee\NewCommittee::class)->name('committees.new');
        Route::livewire('{uid}/committees/{ou}/new-role', \App\Livewire\Committee\NewRole::class)->name('committees.roles.new');
        Route::livewire('{uid}/committees/{ou}/edit', \App\Livewire\Committee\EditCommittee::class)->name('committees.edit');
        Route::livewire('{uid}/committees/{ou}/role/{cn}/edit', \App\Livewire\Committee\EditRole::class)->name('committees.roles.edit');
        Route::livewire('{uid}/committees/{ou}/role/{cn}/new-member', \App\Livewire\Committee\AddUserToRole::class)->name('committees.roles.add-member');
        Route::livewire('{uid}/committees/{ou}/role/{cn}/membership/{id}', \App\Livewire\Committee\EditRoleMembership::class)->name('committees.roles.members.edit');
        Route::livewire('{uid}/tools/compare-email-list', \App\Livewire\Tools\CompareEmailList::class)->name('tools.compare-email-list');
        Route::livewire('{uid}/tools', \App\Livewire\Tools\ToolsDashboard::class)->name('tools.dashboard');
        // end mod
    });

    Route::middleware(['communityAdmin'])->group(function (){
        // admin
        Route::livewire('{uid}/new-admin', \App\Livewire\Realm\NewAdmin::class)->name('realms.admins.new');
        Route::livewire('{uid}/groups', \App\Livewire\Group\ListGroups::class)->name('realms.groups');
        Route::livewire('{uid}/groups/{cn}/edit', \App\Livewire\Group\EditGroup::class)->name('realms.groups.edit');
        Route::livewire('{uid}/new-group', \App\Livewire\Group\NewGroup::class)->name('realms.groups.new');
        Route::livewire('{uid}/group/{cn}/roles', \App\Livewire\Group\ListRolesInGroup::class)->name('realms.groups.roles');
        Route::livewire('{uid}/group/{cn}/add-role', \App\Livewire\Group\AddRoleToGroup::class)->name('realms.groups.roles.add');
        Route::livewire('{uid}/domains', \App\Livewire\Realm\ListDomains::class)->name('realms.domains');
        Route::livewire('{uid}/new-domain', \App\Livewire\Realm\NewDomain::class)->name('realms.domains.new');
        Route::livewire('{uid}/edit', \App\Livewire\Realm\EditRealm::class)->name('realms.edit');
        // end admin
    });

    // fine grained permissions
    Route::livewire('{uid}/new-mod', \App\Livewire\Realm\NewModerator::class)->name('realms.mods.new')
        ->can('add_moderator', 'uid');

    Route::middleware([SuperAdminMiddleware::class])->group(function (){
        Route::livewire('{uid}/new-member', \App\Livewire\Realm\NewMember::class)->name('realms.members.new');
        Route::livewire('superadmins', \App\Livewire\ListSuperUsers::class)->name('superadmins.list');
        Route::livewire('add-superadmin', \App\Livewire\AddSuperUser::class)->name('superadmins.add');
        Route::livewire('new-realm', \App\Livewire\Realm\NewRealm::class)->name('realms.new');
    });
    // end auth verified
});


// guest routes
Route::get('about', function (){
    return redirect(config('app.about_url'));
})->name('about');

Route::get('privacy', function (){
    return redirect(config('app.privacy_url'));
})->name('privacy');

Route::get('terms', function (){
    return redirect(config('app.terms_url'));
})->name('terms');

Route::get('source-code', function (){
    return redirect("https://github.com/openadministration/stumv");
})->name('source-code');

require __DIR__.'/auth.php';
