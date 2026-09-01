<?php

use App\Livewire\Tools\InviteUser;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the role select only offers roles that belong to the selected committee', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committeeA = TestLdap::makeCommittee($community);
    $roleA = TestLdap::makeRole($committeeA);
    $committeeB = TestLdap::makeCommittee($community);
    $roleB = TestLdap::makeRole($committeeB);

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('selected_committee_dn', $committeeA->getDn())
        ->assertSee($roleA->getFirstAttribute('cn'))
        ->assertDontSee($roleB->getFirstAttribute('cn'));
});

test('adding a role selection queues it and clears the role select for the next pick', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $key = $committee->getDn().'|'.$role->getFirstAttribute('cn');

    $component = Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('selected_committee_dn', $committee->getDn())
        ->set('selected_role_dn', $role->getDn())
        ->call('addRoleSelection')
        ->assertSet('selected_role_dn', '');

    $queued = $component->get('queuedRoleSelections');

    expect($queued)->toHaveKey($key)
        ->and($queued[$key]['committee_dn'])->toBe($committee->getDn())
        ->and($queued[$key]['role_cn'])->toBe($role->getFirstAttribute('cn'));
});

test('a role belonging to a different committee than currently selected is rejected', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committeeA = TestLdap::makeCommittee($community);
    $committeeB = TestLdap::makeCommittee($community);
    $roleB = TestLdap::makeRole($committeeB);

    $component = Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('selected_committee_dn', $committeeA->getDn())
        ->set('selected_role_dn', $roleB->getDn())
        ->call('addRoleSelection')
        ->assertHasErrors('selected_role_dn');

    expect($component->get('queuedRoleSelections'))->toBe([]);
});

test('a committee belonging to a different realm is rejected', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    actingAsAdmin($community);
    $otherCommittee = TestLdap::makeCommittee($otherCommunity);
    $otherRole = TestLdap::makeRole($otherCommittee);

    $component = Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('selected_committee_dn', $otherCommittee->getDn())
        ->set('selected_role_dn', $otherRole->getDn())
        ->call('addRoleSelection');

    expect($component->get('queuedRoleSelections'))->toBe([]);
});

test('adding the same role twice is rejected and only queued once', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);

    $component = Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('selected_committee_dn', $committee->getDn())
        ->set('selected_role_dn', $role->getDn())
        ->call('addRoleSelection')
        ->set('selected_committee_dn', $committee->getDn())
        ->set('selected_role_dn', $role->getDn())
        ->call('addRoleSelection')
        ->assertHasErrors('selected_role_dn');

    expect($component->get('queuedRoleSelections'))->toHaveCount(1);
});

test('removing a queued role selection removes it', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $key = $committee->getDn().'|'.$role->getFirstAttribute('cn');

    $component = Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('selected_committee_dn', $committee->getDn())
        ->set('selected_role_dn', $role->getDn())
        ->call('addRoleSelection')
        ->call('removeRoleSelection', $key);

    expect($component->get('queuedRoleSelections'))->toBe([]);
});

test('sending an invitation persists it with the queued role selections, scoped to the acting realm, and redirects to the pending list', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $email = 'invitee-'.uniqid().'@not-a-registerable-domain.invalid';

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('email', $email)
        ->set('selected_committee_dn', $committee->getDn())
        ->set('selected_role_dn', $role->getDn())
        ->call('addRoleSelection')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('tools.invitations', ['realm' => $community->getShortCode()]));

    $invitation = Invitation::where('email', $email)->where('realm', $community->getShortCode())->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->accepted_at)->toBeNull()
        ->and($invitation->invited_by_username)->not->toBeNull()
        ->and($invitation->roleSelections)->toHaveCount(1)
        ->and($invitation->roleSelections->first()->committee_dn)->toBe($committee->getDn())
        ->and($invitation->roleSelections->first()->role_cn)->toBe($role->getFirstAttribute('cn'));
});

test('sending an invitation with no role selections at all is allowed', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $email = 'invitee-'.uniqid().'@not-a-registerable-domain.invalid';

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('email', $email)
        ->call('save')
        ->assertHasNoErrors();

    $invitation = Invitation::where('email', $email)->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->roleSelections)->toHaveCount(0);
});

test('inviting an email that already has a pending invitation in this realm is rejected', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $email = 'invitee-'.uniqid().'@not-a-registerable-domain.invalid';
    Invitation::create(['realm' => $community->getShortCode(), 'email' => $email, 'expires_at' => now()->addDays(7)]);

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('email', $email)
        ->call('save')
        ->assertHasErrors('email');

    expect(Invitation::where('email', $email)->count())->toBe(1);
});

test('an already-accepted invitation does not block a new invite to the same email', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $email = 'invitee-'.uniqid().'@not-a-registerable-domain.invalid';
    Invitation::create(['realm' => $community->getShortCode(), 'email' => $email, 'expires_at' => now()->addDays(7), 'accepted_at' => now()]);

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('email', $email)
        ->call('save')
        ->assertHasNoErrors();

    expect(Invitation::where('email', $email)->count())->toBe(2);
});

test('a pending invitation in a different realm does not block inviting the same email here', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    actingAsAdmin($community);
    $email = 'invitee-'.uniqid().'@not-a-registerable-domain.invalid';
    Invitation::create(['realm' => $otherCommunity->getShortCode(), 'email' => $email, 'expires_at' => now()->addDays(7)]);

    Livewire::test(InviteUser::class, ['realm' => $community])
        ->set('email', $email)
        ->call('save')
        ->assertHasNoErrors();
});
