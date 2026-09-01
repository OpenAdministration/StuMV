<?php

use App\Ldap\Community;
use App\Ldap\Group as LdapGroup;
use App\Ldap\User as LdapUser;
use App\Models\GroupMembership;
use App\Models\Invitation;
use App\Models\InvitationRoleSelection;
use App\Models\RoleMembership;
use App\Models\User as DbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * Accepting an invitation (App\Livewire\AcceptInvitation, mounted at
 * {realm}/invitation/{invitation}/{hash}) is the one registration path that
 * deliberately skips App\Rules\DomainRegistrationRule - see
 * App\Livewire\Tools\InviteUser and tests/Feature/Livewire/Tools/InviteUserTest.php
 * for how the invitation itself is created.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Unique per run so repeated local runs don't collide; removed in afterEach.
    $this->username = 'invacc'.bin2hex(random_bytes(4));
    $this->password = 'Aa1!'.bin2hex(random_bytes(8));
    purgeAcceptedUser($this->username);
});

afterEach(function (): void {
    purgeAcceptedUser($this->username);
});

/** Delete the LDAP user an acceptance created. */
function purgeAcceptedUser(string $username): void
{
    LdapUser::findByUsername($username)?->delete();
}

function invitationUrl(Community $community, Invitation $invitation, ?string $hash = null): string
{
    return URL::temporarySignedRoute('invitation.accept', $invitation->expires_at, [
        'realm' => $community->getShortCode(),
        'invitation' => $invitation->id,
        'hash' => $hash ?? sha1($invitation->email),
    ]);
}

test('a valid invitation link is reachable and shows the accept-invitation form', function (): void {
    $community = newCommunity();
    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => $this->username.'@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
    ]);

    $this->get(invitationUrl($community, $invitation))
        ->assertStatus(200)
        ->assertSeeLivewire('accept-invitation');
});

test('accepting an invitation registers the account outside the domain whitelist, verifies the email and grants the pre-selected role', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $email = $this->username.'@not-a-registerable-domain.invalid';

    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => $email,
        'expires_at' => now()->addDays(7),
    ]);
    InvitationRoleSelection::create([
        'invitation_id' => $invitation->id,
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    Livewire::test('accept-invitation', [
        'realm' => $community,
        'invitation' => $invitation,
        'hash' => sha1($email),
    ])
        ->set('first_name', 'Invited')
        ->set('last_name', 'Person')
        ->set('username', $this->username)
        ->set('password', $this->password)
        ->set('password_confirmation', $this->password)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realm.login', ['realm' => $community->getShortCode()]));

    $ldapUser = LdapUser::findByUsername($this->username);
    expect($ldapUser)->not->toBeNull()
        ->and($ldapUser->getFirstAttribute('mail'))->toBe($email)
        ->and($ldapUser->getDn())->toEndWith(','.$community->peopleDn());

    $dbUser = DbUser::where('username', $this->username)->first();
    expect($dbUser)->not->toBeNull()
        ->and($dbUser->realm)->toBe($community->getShortCode())
        ->and($dbUser->email_verified_at)->not->toBeNull();

    expect(RoleMembership::where('username', $this->username)
        ->where('committee_dn', $committee->getDn())
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->exists())->toBeTrue();

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('accepting an invitation with a pre-selected role also adds the user to the actual LDAP role, not just the DB', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $email = $this->username.'@not-a-registerable-domain.invalid';

    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => $email,
        'expires_at' => now()->addDays(7),
    ]);
    InvitationRoleSelection::create([
        'invitation_id' => $invitation->id,
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    Livewire::test('accept-invitation', [
        'realm' => $community,
        'invitation' => $invitation,
        'hash' => sha1($email),
    ])
        ->set('first_name', 'Invited')
        ->set('last_name', 'Person')
        ->set('username', $this->username)
        ->set('password', $this->password)
        ->set('password_confirmation', $this->password)
        ->call('save');

    $ldapMembers = $committee->roles()->where('cn', $role->getFirstAttribute('cn'))->first()
        ->members()->get()
        ->map(fn ($user) => $user->getFirstAttribute('uid'));

    expect($ldapMembers)->toContain($this->username);
});

test('accepting an invitation also adds the user to any realm group the granted role is mapped into', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $group = TestLdap::makeGroup($community);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $email = $this->username.'@not-a-registerable-domain.invalid';

    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => $email,
        'expires_at' => now()->addDays(7),
    ]);
    InvitationRoleSelection::create([
        'invitation_id' => $invitation->id,
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    Livewire::test('accept-invitation', [
        'realm' => $community,
        'invitation' => $invitation,
        'hash' => sha1($email),
    ])
        ->set('first_name', 'Invited')
        ->set('last_name', 'Person')
        ->set('username', $this->username)
        ->set('password', $this->password)
        ->set('password_confirmation', $this->password)
        ->call('save');

    $groupMembers = LdapGroup::findOrFail($group->getDn())->members()->get()
        ->map(fn ($user) => $user->getFirstAttribute('uid'));

    expect($groupMembers)->toContain($this->username);
});

test('a tampered hash is rejected even though the signature itself is valid', function (): void {
    $community = newCommunity();
    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => $this->username.'@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
    ]);

    $this->get(invitationUrl($community, $invitation, sha1('someone-else@example.test')))
        ->assertStatus(403);
});

test('the link cannot be replayed under a different, otherwise valid realm segment', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => $this->username.'@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
    ]);

    // realm/invitation/hash are all part of the signed payload - swapping the
    // realm segment for another real (existing) community must invalidate
    // the signature, not just fail App\Livewire\AcceptInvitation's own
    // realm-match check.
    $validUrl = invitationUrl($community, $invitation);
    $swapped = str_replace('/'.$community->getShortCode().'/invitation/', '/'.$otherCommunity->getShortCode().'/invitation/', $validUrl);

    $this->get($swapped)->assertStatus(403);
});

test('an expired invitation link is rejected', function (): void {
    $community = newCommunity();
    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => $this->username.'@not-a-registerable-domain.invalid',
        'expires_at' => now()->subMinute(),
    ]);

    $this->get(invitationUrl($community, $invitation))->assertStatus(403);
});

test('an already-accepted invitation link 404s on a second visit', function (): void {
    $community = newCommunity();
    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => $this->username.'@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    $this->get(invitationUrl($community, $invitation))->assertStatus(404);
});

test('the admin realm has no invitation-acceptance form', function (): void {
    $invitation = Invitation::create([
        'realm' => Community::ADMIN_REALM_UID,
        'email' => $this->username.'@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
    ]);

    $url = URL::temporarySignedRoute('invitation.accept', $invitation->expires_at, [
        'realm' => Community::ADMIN_REALM_UID,
        'invitation' => $invitation->id,
        'hash' => sha1($invitation->email),
    ]);

    $this->get($url)->assertNotFound();
});
