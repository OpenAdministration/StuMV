<?php

namespace App\Providers;

use App\Models\Committee;
use App\Policies\CommitteePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use LDAP\Connection;
use LdapRecord\Auth\BindException;
use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Ldap;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        Committee::class => CommitteePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
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
