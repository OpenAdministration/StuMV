<?php

use App\Livewire\Committee\AddUserToRole;
use App\Livewire\Committee\EditRoleMembership;
use App\Livewire\Committee\ListRoleMembers;
use App\Livewire\Committee\TerminateRoleMemberships;
use App\Models\ActivityLog;
use App\Models\RoleMembership;
use App\Support\IdentityProviderGroupRoleSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('adding a person to a role logs all fields of the membership', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    $moderator = actingAsModerator($community);

    Livewire::test(AddUserToRole::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('usernames', [$member->username])
        ->set('start_date', '2026-10-01')
        ->set('decision_date', '2026-09-15')
        ->set('comment', 'Wahl')
        ->call('save')
        ->assertHasNoErrors();

    $membership = RoleMembership::where('username', $member->username)->sole();
    $log = ActivityLog::sole();
    expect($log->action)->toBe('role_membership.created')
        ->and($log->realm)->toBe($community->getShortCode())
        ->and($log->actor_type)->toBe('user')
        ->and($log->actor_id)->toBe($moderator->uid)
        ->and($log->subject_id)->toBe((string) $membership->id)
        ->and($log->subject_label)->toBe('mitglied (fsr)')
        ->and($log->details)->toBe([
            'person' => $member->uid,
            'role' => 'cn=mitglied,'.$committee->getDn(),
            'from' => '2026-10-01',
            'until' => null,
            'decided' => '2026-09-15',
            'comment' => 'Wahl',
        ]);
});

test('editing a membership logs only the fields that changed', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    $membership = RoleMembership::create(['realm' => $community->getShortCode(), 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => '2026-10-01']);
    actingAsModerator($community);

    Livewire::test(EditRoleMembership::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied', 'id' => $membership->id])
        ->set('end_date', '2027-09-30')
        ->call('save')
        ->assertHasNoErrors();

    expect(ActivityLog::sole()->details)->toBe(['until' => ['old' => null, 'new' => '2027-09-30']]);
});

test('saving a membership unchanged logs nothing', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    $membership = RoleMembership::create(['realm' => $community->getShortCode(), 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => '2026-10-01']);
    actingAsModerator($community);

    Livewire::test(EditRoleMembership::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied', 'id' => $membership->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(ActivityLog::count())->toBe(0);
});

test('terminating memberships logs the end date of each', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $ids = collect([TestLdap::member($community), TestLdap::member($community)])
        ->map(fn ($member) => RoleMembership::create(['realm' => $community->getShortCode(), 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => '2026-01-01'])->id);
    actingAsModerator($community);

    Livewire::test(TerminateRoleMemberships::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('membershipsToTerminate', $ids->all())
        ->set('terminationDate', '2026-11-30')
        ->call('save')
        ->assertHasNoErrors();

    expect(ActivityLog::where('action', 'role_membership.updated')->pluck('subject_id')->all())->toEqualCanonicalizing($ids->map(strval(...))->all())
        ->and(ActivityLog::first()->details)->toBe(['until' => ['old' => null, 'new' => '2026-11-30']]);
});

test('removing a membership logs it with its fields and name', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    $membership = RoleMembership::create(['realm' => $community->getShortCode(), 'role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => '2026-10-01']);
    actingAsModerator($community);

    Livewire::test(ListRoleMembers::class, ['realm' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->call('prepareDeletion', $membership->id)
        ->call('commitDeletion');

    $log = ActivityLog::sole();
    expect($log->action)->toBe('role_membership.deleted')
        ->and($log->subject_label)->toBe('mitglied (fsr)')
        ->and($log->details['from'])->toBe('2026-10-01');
});

test('roles granted and revoked at login are logged with the identity provider as actor', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    $this->actingAs($user);
    $sync = resolve(IdentityProviderGroupRoleSync::class);

    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);
    $sync->apply($provider, $user->username, ['groups' => []]);

    expect(ActivityLog::orderBy('id')->pluck('action')->all())->toBe(['role_membership.created', 'role_membership.deleted'])
        ->and(ActivityLog::pluck('actor_type')->unique()->all())->toBe(['identity_provider'])
        ->and(ActivityLog::pluck('actor_id')->unique()->all())->toBe([$provider->id]);
});
