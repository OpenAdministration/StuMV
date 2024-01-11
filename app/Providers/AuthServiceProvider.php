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
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

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
        VerifyEmail::toMailUsing(static function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject(Lang::get('auth.verification_mail_subject'))
                ->line(Lang::get('auth.verification_mail_line_between_greeting_and_action'))
                ->action(Lang::get('auth.verification_mail_button_action'), $url)
                ->line(Lang::get('auth.verification_mail_line_after_action'));
        });

        ResetPassword::toMailUsing(static function (mixed $notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'mail' => $notifiable->getEmailForPasswordReset(),
            ], false));
            return (new MailMessage)
                ->subject(Lang::get('passwords.reset_mail_subject'))
                ->line(Lang::get('passwords.reset_mail_line_between_greeting_and_action'))
                ->action(Lang::get('passwords.reset_mail_action'), $url)
                ->line(Lang::get('passwords.reset_mail_expire_notice', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
                ->line(Lang::get('passwords.reset_mail_was_not_you_question'));
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
