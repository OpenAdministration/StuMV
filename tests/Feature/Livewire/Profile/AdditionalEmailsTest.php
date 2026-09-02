<?php

use App\Ldap\User as LdapUser;
use App\Livewire\Profile\Profile;
use App\Models\UserAdditionalEmail;
use App\Notifications\VerifyAdditionalEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

function ldapUserOf(string $username, $community): LdapUser
{
    return LdapUser::query()->in($community->peopleDn())->where('uid', '=', $username)->first();
}

function additionalEmailsInLdap(string $username, $community): array
{
    return ldapUserOf($username, $community)->additionalEmails();
}

/** Adds an address through the form, as a person would. */
function addAddressViaProfile($community, string $username, string $address): void
{
    Livewire::test(Profile::class, ['realm' => $community, 'username' => $username])
        ->call('addEmailRow')
        ->set('additionalEmails.0', $address)
        ->call('save')
        ->assertHasNoErrors();
}

function verificationUrlFor(UserAdditionalEmail $row, $community): string
{
    return URL::temporarySignedRoute('profile.emails.verify', now()->addHour(), [
        'realm' => $community->getShortCode(),
        'additionalEmail' => $row->id,
        'hash' => sha1($row->address),
    ]);
}

test('the primary address cannot be changed through the profile, only the additional ones', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    $component = Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username]);

    expect(fn () => $component->set('email', 'taken-over@example.test'))
        ->toThrow(Exception::class, 'Cannot update locked property');

    $component->call('save')->assertHasNoErrors();

    expect(ldapUserOf($user->username, $community)->getFirstAttribute('mail'))->toBe($user->email);
});

test('a newly added address is recorded unverified and stays out of LDAP until confirmed', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');

    $row = UserAdditionalEmail::firstWhere('address', 'alias@example.test');

    expect($row)->not->toBeNull()
        ->and($row->isVerified())->toBeFalse()
        // Not in the directory yet, so identity-provider matching cannot see
        // it and nobody else is blocked from claiming the address.
        ->and(additionalEmailsInLdap($user->username, $community))->toBe([]);

    Notification::assertSentOnDemand(VerifyAdditionalEmail::class);
});

test('following the confirmation link verifies the address and writes it to LDAP', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');
    $row = UserAdditionalEmail::firstWhere('address', 'alias@example.test');

    $this->get(verificationUrlFor($row, $community))->assertRedirect();

    expect($row->fresh()->isVerified())->toBeTrue()
        ->and(additionalEmailsInLdap($user->username, $community))->toBe(['alias@example.test']);
});

test('the confirmation link works without being signed in', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');
    $row = UserAdditionalEmail::firstWhere('address', 'alias@example.test');

    auth()->logout();

    $this->get(verificationUrlFor($row, $community))
        ->assertRedirect(route('realm.login', ['realm' => $community->getShortCode()]));

    expect($row->fresh()->isVerified())->toBeTrue();
});

test('an unsigned confirmation link is rejected', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');
    $row = UserAdditionalEmail::firstWhere('address', 'alias@example.test');

    $this->get(route('profile.emails.verify', [
        'realm' => $community->getShortCode(),
        'additionalEmail' => $row->id,
        'hash' => sha1($row->address),
    ]))->assertStatus(403);

    expect($row->fresh()->isVerified())->toBeFalse();
});

test('a confirmation link whose hash does not match the address is rejected', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');
    $row = UserAdditionalEmail::firstWhere('address', 'alias@example.test');

    $tampered = URL::temporarySignedRoute('profile.emails.verify', now()->addHour(), [
        'realm' => $community->getShortCode(),
        'additionalEmail' => $row->id,
        'hash' => sha1('someone-else@example.test'),
    ]);

    $this->get($tampered)->assertStatus(403);

    expect($row->fresh()->isVerified())->toBeFalse();
});

test('when two accounts request the same address, only the first to confirm gets it', function (): void {
    Notification::fake();
    $community = newCommunity();
    $first = actingAsMember($community);
    $second = TestLdap::member($community);

    addAddressViaProfile($community, $first->username, 'shared@example.test');
    $firstRow = UserAdditionalEmail::firstWhere('username', $first->username);

    actingAsSuperAdmin();
    addAddressViaProfile($community, $second->username, 'shared@example.test');
    $secondRow = UserAdditionalEmail::firstWhere('username', $second->username);

    $this->get(verificationUrlFor($firstRow, $community))->assertRedirect();
    $this->get(verificationUrlFor($secondRow, $community))->assertRedirect();

    expect($firstRow->fresh()->isVerified())->toBeTrue()
        ->and($secondRow->fresh()->isVerified())->toBeFalse()
        ->and(additionalEmailsInLdap($first->username, $community))->toBe(['shared@example.test'])
        ->and(additionalEmailsInLdap($second->username, $community))->toBe([]);
});

test('a confirmed address can be removed again and leaves LDAP with it', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');
    $row = UserAdditionalEmail::firstWhere('address', 'alias@example.test');
    $this->get(verificationUrlFor($row, $community));

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->call('removeEmailRow', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect(additionalEmailsInLdap($user->username, $community))->toBe([])
        ->and(UserAdditionalEmail::count())->toBe(0);
});

test('saving the rest of the profile keeps a confirmed address', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');
    $row = UserAdditionalEmail::firstWhere('address', 'alias@example.test');
    $this->get(verificationUrlFor($row, $community));

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->set('givenName', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect(additionalEmailsInLdap($user->username, $community))->toBe(['alias@example.test']);
});

test('saving the profile again leaves a pending address pending, without duplicating it', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->call('save')
        ->assertHasNoErrors();

    // Asserted on the rows rather than on how many mails went out: a mail is
    // only ever sent where a row is created, and the notification count is not
    // usable here - dispatch()->afterResponse() registers a terminating
    // callback that Livewire's test harness re-runs on every request.
    expect(UserAdditionalEmail::count())->toBe(1)
        ->and(UserAdditionalEmail::first()->isVerified())->toBeFalse();
});

test('the primary address stays in first position once an address is confirmed', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    addAddressViaProfile($community, $user->username, 'alias@example.test');
    $row = UserAdditionalEmail::firstWhere('address', 'alias@example.test');
    $this->get(verificationUrlFor($row, $community));

    // Everything outside identity-provider matching reads the address through
    // getFirstAttribute('mail'), so the primary must never be displaced.
    expect(ldapUserOf($user->username, $community)->getFirstAttribute('mail'))->toBe($user->email);
});

test('an empty row added but never filled in is simply dropped', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->call('addEmailRow')
        ->call('save')
        ->assertHasNoErrors();

    expect(UserAdditionalEmail::count())->toBe(0);
    Notification::assertNothingSent();
});

test('an address another account in this realm already holds is rejected', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);
    $other = TestLdap::member($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->call('addEmailRow')
        ->set('additionalEmails.0', $other->email)
        ->call('save')
        ->assertHasErrors('additionalEmails.0');

    expect(UserAdditionalEmail::count())->toBe(0);
});

test('the account\'s own primary address is rejected as an additional one', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->call('addEmailRow')
        ->set('additionalEmails.0', $user->email)
        ->call('save')
        ->assertHasErrors('additionalEmails.0');
});

test('the same address entered twice is rejected', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->call('addEmailRow')
        ->call('addEmailRow')
        ->set('additionalEmails.0', 'alias@example.test')
        ->set('additionalEmails.1', 'alias@example.test')
        ->call('save')
        ->assertHasErrors('additionalEmails.0');
});

test('an address added to LDAP outside the form is adopted rather than dropped', function (): void {
    Notification::fake();
    $community = newCommunity();
    $user = actingAsMember($community);

    $ldapUser = ldapUserOf($user->username, $community);
    $ldapUser->addAdditionalEmail('legacy@example.test');
    $ldapUser->save();

    Livewire::test(Profile::class, ['realm' => $community, 'username' => $user->username])
        ->call('save')
        ->assertHasNoErrors();

    expect(additionalEmailsInLdap($user->username, $community))->toBe(['legacy@example.test'])
        ->and(UserAdditionalEmail::firstWhere('address', 'legacy@example.test')->isVerified())->toBeTrue();
});
