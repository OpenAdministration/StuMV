<?php

use App\Ldap\User as LdapUser;
use App\Models\AccountDeletionMark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    config(['accounts.inactive_after_days' => 365, 'accounts.deletion_grace_days' => 30]);
});

function markedAccount(): array
{
    $community = newCommunity();
    $ldap = TestLdap::makeUser(community: $community);
    $user = TestLdap::databaseUser($ldap, $community);
    test()->travel(400)->days();
    test()->artisan('app:mark-inactive-accounts', ['realm' => $community->getShortCode()])->assertExitCode(0);

    return [$community, $ldap, $user];
}

function ldapEntryExists(LdapUser $ldap): bool
{
    return LdapUser::query()->findByGuid($ldap->getConvertedGuid()) !== null;
}

test('an account without a login after the deadline is deleted', function (): void {
    [, $ldap, $user] = markedAccount();
    $this->travel(31)->days();

    $this->artisan('app:delete-marked-accounts')->assertExitCode(0);

    expect(ldapEntryExists($ldap))->toBeFalse()
        ->and(User::find($user->id))->toBeNull()
        ->and(AccountDeletionMark::count())->toBe(0);
});

test('nothing is deleted before the deadline or on a dry run', function (): void {
    [, $ldap] = markedAccount();

    $this->artisan('app:delete-marked-accounts')->assertExitCode(0);
    $this->travel(31)->days();
    $this->artisan('app:delete-marked-accounts', ['--dry-run' => true])->assertExitCode(0);

    expect(ldapEntryExists($ldap))->toBeTrue()
        ->and(AccountDeletionMark::count())->toBe(1);
});

test('an account with a login after the email is kept', function (): void {
    [, $ldap, $user] = markedAccount();
    $this->travel(1)->days();
    $user->forceFill(['last_login_at' => Date::now()])->saveQuietly();
    $this->travel(30)->days();

    $this->artisan('app:delete-marked-accounts')->assertExitCode(0);

    expect(ldapEntryExists($ldap))->toBeTrue()
        ->and(AccountDeletionMark::count())->toBe(0);
});

test('the deleted account is removed from its groups', function (): void {
    [$community, $ldap] = markedAccount();
    $group = TestLdap::makeGroup($community);
    TestLdap::attach($group, $ldap);
    $this->travel(31)->days();

    $this->artisan('app:delete-marked-accounts')->assertExitCode(0);

    expect($group->fresh()->getAttribute('uniqueMember'))->not->toContain($ldap->getDn());
});
