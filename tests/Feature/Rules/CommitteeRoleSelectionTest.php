<?php

use App\Rules\CommitteeRoleSelection;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TestLdap;

/**
 * App\Livewire\Tools\InviteUser submits "{committee_dn}|{role_cn}" pillbox
 * values - this rule is the server-side guard confirming a value actually
 * names a role directly under a committee within the given realm, never
 * trusting the submitted DN on its own.
 */
function passesCommitteeRoleSelection(string $realmUid, mixed $value): bool
{
    return Validator::make(
        ['selection' => $value],
        ['selection' => [new CommitteeRoleSelection($realmUid)]]
    )->passes();
}

test('accepts a role that exists directly under a committee in this realm', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);

    expect(passesCommitteeRoleSelection(
        $community->getShortCode(),
        $committee->getDn().'|'.$role->getFirstAttribute('cn')
    ))->toBeTrue();
});

test('rejects a committee/role pair belonging to a different realm', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $otherCommittee = TestLdap::makeCommittee($otherCommunity);
    $otherRole = TestLdap::makeRole($otherCommittee);

    expect(passesCommitteeRoleSelection(
        $community->getShortCode(),
        $otherCommittee->getDn().'|'.$otherRole->getFirstAttribute('cn')
    ))->toBeFalse();
});

test('rejects a role cn that does not exist under the given committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community);

    expect(passesCommitteeRoleSelection(
        $community->getShortCode(),
        $committee->getDn().'|no-such-role'
    ))->toBeFalse();
});

test('rejects a malformed value with no role part', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community);

    expect(passesCommitteeRoleSelection($community->getShortCode(), $committee->getDn()))->toBeFalse();
});
