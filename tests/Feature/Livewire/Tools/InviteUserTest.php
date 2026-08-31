<?php

use App\Livewire\Tools\InviteUser;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('sending an invitation persists it with the staged role selections, scoped to the acting realm', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $email = 'invitee-'.uniqid().'@not-a-registerable-domain.invalid';

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('email', $email)
        ->set('roleSelections', [$committee->getDn().'|'.$role->getFirstAttribute('cn')])
        ->call('save')
        ->assertHasNoErrors();

    $invitation = Invitation::where('email', $email)->where('realm', $community->getShortCode())->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->accepted_at)->toBeNull()
        ->and($invitation->invited_by_username)->not->toBeNull()
        ->and($invitation->roleSelections)->toHaveCount(1)
        ->and($invitation->roleSelections->first()->committee_dn)->toBe($committee->getDn())
        ->and($invitation->roleSelections->first()->role_cn)->toBe($role->getFirstAttribute('cn'));
});

test('a committee/role belonging to a different realm is rejected and nothing is persisted', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    actingAsAdmin($community);
    $otherCommittee = TestLdap::makeCommittee($otherCommunity);
    $otherRole = TestLdap::makeRole($otherCommittee);

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('email', 'invitee-'.uniqid().'@not-a-registerable-domain.invalid')
        ->set('roleSelections', [$otherCommittee->getDn().'|'.$otherRole->getFirstAttribute('cn')])
        ->call('save')
        ->assertHasErrors('roleSelections.0');

    expect(Invitation::count())->toBe(0);
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

    Livewire::test(InviteUser::class, ['realm' => $community])
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

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->call('revoke', $invitation->id);

    expect(Invitation::find($invitation->id))->toBeNull();
});

test('the pending invitations list only shows this realm\'s own unaccepted invitations', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    actingAsAdmin($community);

    Invitation::create(['realm' => $community->getShortCode(), 'email' => 'own@not-a-registerable-domain.invalid', 'expires_at' => now()->addDays(7)]);
    Invitation::create(['realm' => $otherCommunity->getShortCode(), 'email' => 'foreign@not-a-registerable-domain.invalid', 'expires_at' => now()->addDays(7)]);
    Invitation::create(['realm' => $community->getShortCode(), 'email' => 'accepted@not-a-registerable-domain.invalid', 'expires_at' => now()->addDays(7), 'accepted_at' => now()]);

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->assertSee('own@not-a-registerable-domain.invalid')
        ->assertDontSee('foreign@not-a-registerable-domain.invalid')
        ->assertDontSee('accepted@not-a-registerable-domain.invalid');
});
