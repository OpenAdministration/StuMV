<?php

use App\Models\RoleMembership;
use App\Support\MembershipCertificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('memberships returns every role membership for the user in that realm', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $user = TestLdap::member($community);

    RoleMembership::create([
        'realm' => $community->getShortCode(),
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $user->username,
        'from' => today()->subMonth(),
    ]);

    $memberships = MembershipCertificate::memberships($community->getShortCode(), $user->username, false);

    expect($memberships)->toHaveCount(1)
        ->and($memberships[0]['role']->getDn())->toBe($role->getDn());
});

test('memberships with onlyActive excludes memberships that have ended', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $user = TestLdap::member($community);

    RoleMembership::create([
        'realm' => $community->getShortCode(),
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $user->username,
        'from' => today()->subYear(),
        'until' => today()->subMonth(),
    ]);

    expect(MembershipCertificate::memberships($community->getShortCode(), $user->username, true))->toHaveCount(0)
        ->and(MembershipCertificate::memberships($community->getShortCode(), $user->username, false))->toHaveCount(1);
});

test('memberships stays scoped to the requested realm even for a shared username', function (): void {
    $realmA = newCommunity();
    $realmB = newCommunity();
    $committeeA = TestLdap::makeCommittee($realmA, 'fsr');
    $committeeB = TestLdap::makeCommittee($realmB, 'fsr');
    TestLdap::makeRole($committeeA, 'mitglied');
    TestLdap::makeRole($committeeB, 'mitglied');

    // Same username, independently existing in both realms - the "user"
    // row's (username, realm) FK requires a real account per realm.
    $username = 'shared'.bin2hex(random_bytes(3));
    TestLdap::databaseUser(TestLdap::makeUser($username, $realmA), $realmA);
    TestLdap::databaseUser(TestLdap::makeUser($username, $realmB), $realmB);

    RoleMembership::create([
        'realm' => $realmA->getShortCode(),
        'role_cn' => 'mitglied',
        'committee_dn' => $committeeA->getDn(),
        'username' => $username,
        'from' => today()->subMonth(),
    ]);

    expect(MembershipCertificate::memberships($realmA->getShortCode(), $username, false))->toHaveCount(1)
        ->and(MembershipCertificate::memberships($realmB->getShortCode(), $username, false))->toHaveCount(0);
});

test('pdf passes the full name and, when given, the community label to the view', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $fullName = $user->ldap()->getFirstAttribute('cn');
    $description = $community->getFirstAttribute('description');

    $withCommunityPdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.memberships', Mockery::on(fn (array $data): bool => $data['fullName'] === $fullName
            && $data['community'] === $description
            && $data['memberships'] === []))
        ->andReturn($withCommunityPdf);

    expect(MembershipCertificate::pdf($community->getShortCode(), $user->username, $description))->toBe($withCommunityPdf);

    $withoutCommunityPdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.memberships', Mockery::on(fn (array $data): bool => $data['community'] === null))
        ->andReturn($withoutCommunityPdf);

    expect(MembershipCertificate::pdf($community->getShortCode(), $user->username, null))->toBe($withoutCommunityPdf);
});

test('download streams a PDF under the given filename', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);

    $response = MembershipCertificate::download($community->getShortCode(), $user->username, null, 'test-export.pdf');

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Disposition'))->toContain('test-export.pdf');
});
