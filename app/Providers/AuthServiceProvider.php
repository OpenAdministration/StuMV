<?php

namespace App\Providers;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Domain;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Models\RoleMembership;
use App\Models\User;
use App\Policies\CommitteePolicy;
use App\Policies\CommunityPolicy;
use App\Policies\DomainPolicy;
use App\Policies\GroupPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // db model
        User::class => UserPolicy::class,
        RoleMembership::class => MembershipPolicy::class,
        // ldap
        Community::class => CommunityPolicy::class,
        Committee::class => CommitteePolicy::class,
        Role::class => RolePolicy::class,
        Group::class => GroupPolicy::class,
        Domain::class => DomainPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return url(route('password.reset', [
                'token' => $token,
                'mail' => $user->getEmailForPasswordReset(),
            ], false));
        });
        /*
        if(App::isLocal()){
            try{
                $ldapConnection = Container::getConnection('default');
                $ldapConnection->connect();
            } catch (\Exception $exception){
                DirectoryEmulator::setup('default');
            }
        }
        */
    }
}
