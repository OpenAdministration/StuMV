<?php

use App\Livewire\Tools\ListInvitations;
use App\Models\Invitation;
use App\Models\InvitationRoleSelection;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the pending invitations list only shows this realm\'s own unaccepted invitations', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    actingAsAdmin($community);

    Invitation::create(['realm' => $community->getShortCode(), 'email' => 'own@not-a-registerable-domain.invalid', 'expires_at' => now()->addDays(7)]);
    Invitation::create(['realm' => $otherCommunity->getShortCode(), 'email' => 'foreign@not-a-registerable-domain.invalid', 'expires_at' => now()->addDays(7)]);
    Invitation::create(['realm' => $community->getShortCode(), 'email' => 'accepted@not-a-registerable-domain.invalid', 'expires_at' => now()->addDays(7), 'accepted_at' => now()]);

    Livewire::test(ListInvitations::class, ['realm' => $community])
        ->assertSee('own@not-a-registerable-domain.invalid')
        ->assertDontSee('foreign@not-a-registerable-domain.invalid')
        ->assertDontSee('accepted@not-a-registerable-domain.invalid');
});

test('the list shows the committee/role labels for a pending invitation\'s staged selections', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);

    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => 'invitee@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
    ]);
    InvitationRoleSelection::create([
        'invitation_id' => $invitation->id,
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    Livewire::test(ListInvitations::class, ['realm' => $community])
        ->assertSee($committee->getFirstAttribute('description'))
        ->assertSee($role->getFirstAttribute('description'));
});

test('revoking an invitation only deletes it when it belongs to the acting realm', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    actingAsAdmin($community);

    $foreignInvitation = Invitation::create([
        'realm' => $otherCommunity->getShortCode(),
        'email' => 'foreign@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
    ]);

    Livewire::test(ListInvitations::class, ['realm' => $community])
        ->call('revoke', $foreignInvitation->id);

    expect(Invitation::find($foreignInvitation->id))->not->toBeNull();
});

test('revoking an invitation belonging to the acting realm deletes it', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => 'own@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
    ]);

    Livewire::test(ListInvitations::class, ['realm' => $community])
        ->call('revoke', $invitation->id);

    expect(Invitation::find($invitation->id))->toBeNull();
});

test('resending an invitation refreshes its expiry and re-sends the notification', function (): void {
    Notification::fake();
    $community = newCommunity();
    actingAsAdmin($community);

    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => 'invitee@not-a-registerable-domain.invalid',
        'expires_at' => now()->subDay(),
    ]);

    Livewire::test(ListInvitations::class, ['realm' => $community])
        ->call('resend', $invitation->id);

    expect($invitation->fresh()->expires_at->isFuture())->toBeTrue();
    Notification::assertSentOnDemand(UserInvitation::class);
});

test('resending an invitation belonging to a different realm does nothing', function (): void {
    Notification::fake();
    $community = newCommunity();
    $otherCommunity = newCommunity();
    actingAsAdmin($community);

    $foreignInvitation = Invitation::create([
        'realm' => $otherCommunity->getShortCode(),
        'email' => 'foreign@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
    ]);
    $originalExpiry = $foreignInvitation->expires_at;

    Livewire::test(ListInvitations::class, ['realm' => $community])
        ->call('resend', $foreignInvitation->id);

    expect($foreignInvitation->fresh()->expires_at->equalTo($originalExpiry))->toBeTrue();
    Notification::assertNothingSent();
});

test('resending an already-accepted invitation does nothing', function (): void {
    Notification::fake();
    $community = newCommunity();
    actingAsAdmin($community);

    $invitation = Invitation::create([
        'realm' => $community->getShortCode(),
        'email' => 'accepted@not-a-registerable-domain.invalid',
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    Livewire::test(ListInvitations::class, ['realm' => $community])
        ->call('resend', $invitation->id);

    Notification::assertNothingSent();
});
