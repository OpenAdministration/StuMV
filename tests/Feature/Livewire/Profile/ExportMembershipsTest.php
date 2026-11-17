<?php

use App\Enums\LetterSender;
use App\Ldap\Community;
use App\Livewire\Profile\ExportMemberships;
use App\Models\MembershipExportTemplate;
use App\Models\RoleMembership;
use App\Support\CoverLetter;
use App\Support\MembershipCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/** @return array{0: RoleMembership, 1: RoleMembership} an active and an ended membership */
function twoMemberships(Community $community, string $username): array
{
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    TestLdap::makeRole($committee, 'sprecher');

    $base = ['realm' => $community->getShortCode(), 'committee_dn' => $committee->getDn(), 'username' => $username];

    return [
        RoleMembership::create([...$base, 'role_cn' => 'mitglied', 'from' => today()->subYear()]),
        RoleMembership::create([...$base, 'role_cn' => 'sprecher', 'from' => today()->subYears(2), 'until' => today()->subYear()]),
    ];
}

test('every membership, ended ones included, is preselected', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    [$active, $ended] = twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->assertStatus(200)
        ->assertSet('selected', [(string) $active->id, (string) $ended->id])
        ->assertSee('Role mitglied')
        ->assertSee('Role sprecher');
});

test('all memberships can be deselected and selected again', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    [$active, $ended] = twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->call('deselectAll')
        ->assertSet('selected', [])
        ->call('selectAll')
        ->assertSet('selected', [(string) $active->id, (string) $ended->id]);
});

test('without memberships a back button leads to where the page was opened from', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    $from = route('profile', ['realm' => $community->getShortCode(), 'username' => $user->username]);

    $this->withHeader('referer', $from)
        ->get(route('profile.memberships.export', ['realm' => $community->getShortCode(), 'username' => $user->username]))
        ->assertOk()
        ->assertSee(__('profile.no_memberships_found'))
        ->assertSee(__('common.back'))
        ->assertSee('href="'.$from.'"', false);
});

test('exporting requires at least one selected membership', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->set('selected', [])
        ->call('export')
        ->assertHasErrors('selected');
});

test('exporting a selection downloads a PDF', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    [$active] = twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->set('selected', [(string) $active->id])
        ->call('export')
        ->assertHasNoErrors()
        ->assertFileDownloaded(strtolower(trans('profile.memberships')).'_'.$user->username.'.pdf');
});

test('the PDF only contains the selected memberships', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    [$active, $ended] = twoMemberships($community, $user->username);
    $realm = $community->getShortCode();

    $all = MembershipCertificate::pdf($realm, $user->username, null, [$active->id, $ended->id]);
    $one = MembershipCertificate::pdf($realm, $user->username, null, [$active->id]);

    // one TR for the head plus one per membership
    expect(substr_count($all, '/S /TR'))->toBe(3)
        ->and(substr_count($one, '/S /TR'))->toBe(2);
});

test('ids of another users memberships are ignored', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $other = TestLdap::member($community);
    [$foreign] = twoMemberships($community, $other->username);

    $pdf = MembershipCertificate::pdf($community->getShortCode(), $user->username, null, [$foreign->id]);

    expect(substr_count($pdf, '/S /TR'))->toBe(1);
});

test('a plain member cannot export someone else memberships', function (): void {
    $community = newCommunity();
    $other = TestLdap::member($community);
    actingAsMember($community);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $other->username])
        ->assertForbidden();
});

test('a moderator can export a member memberships', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    actingAsModerator($community);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $member->username])
        ->assertStatus(200);
});

/** Position of each text in the rendered table, to compare row order. */
function rowOrder(string $html, string ...$texts): array
{
    return array_map(fn (string $text): int|false => strpos($html, $text), $texts);
}

test('memberships are sorted newest first by default', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    twoMemberships($community, $user->username);

    $html = Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])->html();

    [$active, $ended] = rowOrder($html, 'Role mitglied', 'Role sprecher');
    expect($active)->toBeLessThan($ended);
});

test('memberships can be sorted by role in both directions', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    twoMemberships($community, $user->username);

    $component = Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->call('sortBy', 'role');
    [$mitglied, $sprecher] = rowOrder($component->html(), 'Role mitglied', 'Role sprecher');
    expect($mitglied)->toBeLessThan($sprecher);

    $component->call('sortBy', 'role');
    [$mitglied, $sprecher] = rowOrder($component->html(), 'Role mitglied', 'Role sprecher');
    expect($sprecher)->toBeLessThan($mitglied);
});

test('sorting by end date puts open-ended memberships last', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    twoMemberships($community, $user->username);

    $html = Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->call('sortBy', 'until')
        ->html();

    [$active, $ended] = rowOrder($html, 'Role mitglied', 'Role sprecher');
    expect($ended)->toBeLessThan($active);
});

test('sorting keeps the selection and ignores unknown columns', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    [$active] = twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->set('selected', [(string) $active->id])
        ->call('sortBy', 'committee')
        ->call('sortBy', 'comment')
        ->assertSet('sortField', 'committee')
        ->assertSet('selected', [(string) $active->id]);
});

test('without templates there is nothing to choose and the export has no cover letter', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);
    twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->assertDontSee(__('profile.export_template'))
        ->call('export')
        ->assertHasNoErrors()
        ->assertFileDownloaded();
});

test('with several templates one has to be chosen', function (): void {
    $community = newCommunity();
    $realm = $community->getShortCode();
    MembershipExportTemplate::create(['realm' => $realm, 'name' => 'Nur Übersicht']);
    $letter = MembershipExportTemplate::create(['realm' => $realm, 'name' => 'Nachweis für das Landesamt', 'with_letter' => true, 'body' => '<p>Text</p>']);
    $user = actingAsMember($community);
    twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->assertSet('template', '')
        ->assertSee(['Nur Übersicht', 'Nachweis für das Landesamt'])
        ->call('export')
        ->assertHasErrors(['template' => 'required'])
        ->set('template', (string) $letter->id)
        ->assertSee(__('profile.cover_letter_no_contact'))
        ->call('export')
        ->assertHasNoErrors()
        ->assertFileDownloaded(strtolower(trans('profile.memberships')).'_'.$user->username.'.pdf');
});

test('a single template is preselected', function (): void {
    $community = newCommunity();
    $template = MembershipExportTemplate::create(['realm' => $community->getShortCode(), 'name' => 'Nur Übersicht']);
    $user = actingAsMember($community);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->assertSet('template', (string) $template->id);
});

test('a template of another realm cannot be chosen', function (): void {
    $community = newCommunity();
    MembershipExportTemplate::create(['realm' => $community->getShortCode(), 'name' => 'Eigene']);
    $foreign = MembershipExportTemplate::create(['realm' => newCommunity()->getShortCode(), 'name' => 'Fremde']);
    $user = actingAsMember($community);
    twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->set('template', (string) $foreign->id)
        ->call('export')
        ->assertHasErrors(['template' => 'in']);
});

test('a letter sent by the exporting person warns when their profile has no address', function (): void {
    $community = newCommunity();
    MembershipExportTemplate::create([
        'realm' => $community->getShortCode(),
        'name' => 'Persönliches Anschreiben',
        'with_letter' => true,
        'sender' => LetterSender::User,
        'body' => '<p>[person.name] war aktiv.</p>',
    ]);
    $user = actingAsMember($community);
    twoMemberships($community, $user->username);

    Livewire::test(ExportMemberships::class, ['realm' => $community, 'username' => $user->username])
        ->assertSee(__('profile.cover_letter_no_profile_address'))
        ->call('export')
        ->assertHasNoErrors()
        ->assertFileDownloaded();
});

test('the address fields are put together in DIN 5008 order', function (): void {
    $address = CoverLetter::recipientAddress([
        'organisation' => 'Universität',
        'unit' => 'Referat Finanzen',
        'name' => 'Frau Erika Muster',
        'street' => 'Hauptstraße',
        'house_number' => '1',
        'postal_code' => '1010',
        'city' => 'Wien',
        'country' => 'Österreich',
    ]);

    expect($address)->toBe("Universität\nReferat Finanzen\nFrau Erika Muster\nHauptstraße 1\n1010 Wien\nÖSTERREICH");
});
