<?php

use App\Models\AccountDeletionMark;
use App\Notifications\InactiveAccountNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    config(['accounts.inactive_after_days' => 365, 'accounts.deletion_grace_days' => 30]);
});

function markInactive(string $realm, array $options = []): void
{
    test()->artisan('app:mark-inactive-accounts', ['realm' => $realm, ...$options])->assertExitCode(0);
}

test('the owner of an inactive account is asked by email and the account is marked', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    $this->travel(400)->days();

    markInactive($community->getShortCode());

    $mark = AccountDeletionMark::sole();
    expect($mark->ldap_uid)->toBe($ldap->getConvertedGuid())
        ->and($mark->email)->toBe($ldap->getFirstAttribute('mail'))
        ->and($mark->delete_after->toDateString())->toBe(Date::today()->addDays(30)->toDateString());
    Notification::assertSentOnDemand(
        InactiveAccountNotice::class,
        fn (InactiveAccountNotice $notice, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $ldap->getFirstAttribute('mail'),
    );
});

test('a marked account is not asked a second time', function (): void {
    $community = newCommunity();
    TestLdap::makeUser(community: $community);
    $this->travel(400)->days();

    markInactive($community->getShortCode());
    markInactive($community->getShortCode());

    expect(AccountDeletionMark::count())->toBe(1);
    Notification::assertSentOnDemandTimes(InactiveAccountNotice::class, 1);
});

test('active, locked and unreachable accounts are left alone', function (): void {
    $community = newCommunity();
    $loggedIn = TestLdap::makeUser(community: $community);
    $locked = TestLdap::makeUser(community: $community);
    $locked->setFirstAttribute('pwdAccountLockedTime', '000001010000Z')->save();
    $withoutMail = TestLdap::makeUser(community: $community);
    $withoutMail->setFirstAttribute('mail', null)->save();
    $this->travel(400)->days();
    TestLdap::databaseUser($loggedIn, $community)->forceFill(['last_login_at' => Date::now()->subDays(10)])->save();

    markInactive($community->getShortCode());

    expect(AccountDeletionMark::count())->toBe(0);
    Notification::assertNothingSent();
});

test('nothing happens while the check is not configured or on a dry run', function (): void {
    $community = newCommunity();
    TestLdap::makeUser(community: $community);
    $this->travel(400)->days();

    markInactive($community->getShortCode(), ['--dry-run' => true]);
    config(['accounts.inactive_after_days' => null]);
    markInactive($community->getShortCode());

    expect(AccountDeletionMark::count())->toBe(0);
    Notification::assertNothingSent();
});

test('logging in removes the mark', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    $user = TestLdap::databaseUser($ldap, $community);
    $this->travel(400)->days();
    markInactive($community->getShortCode());

    Auth::login($user);

    expect(AccountDeletionMark::count())->toBe(0)
        ->and($user->fresh()->last_login_at)->not->toBeNull();
});

test('a login recorded after the email removes the mark on the next run', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    $user = TestLdap::databaseUser($ldap, $community);
    $this->travel(400)->days();
    markInactive($community->getShortCode());

    // e.g. the login event fired on another app server before the mark was written
    $this->travel(1)->days();
    $user->forceFill(['last_login_at' => Date::now()])->saveQuietly();
    markInactive($community->getShortCode());

    expect(AccountDeletionMark::count())->toBe(0);
});

test('the mark of an account deleted from LDAP is removed', function (): void {
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    $this->travel(400)->days();
    markInactive($community->getShortCode());

    $ldap->delete();
    markInactive($community->getShortCode());

    expect(AccountDeletionMark::count())->toBe(0);
});
