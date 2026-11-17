<?php

use App\Enums\ExportDateFormat;
use App\Enums\LetterSender;
use App\Ldap\Community;
use App\Ldap\Domain;
use App\Livewire\Realm\ListRealms;
use App\Livewire\Realm\NewRealm;
use App\Livewire\Settings\Domain\NewDomain;
use App\Livewire\Settings\EditRealm;
use App\Livewire\Settings\MembershipExport\EditMembershipExportTemplate;
use App\Livewire\Settings\MembershipExport\ListMembershipExportTemplates;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\Invitation;
use App\Models\MembershipExportTemplate;
use App\Models\RealmContact;
use App\Models\RoleMembership;
use App\Models\User;
use App\Models\UserAdditionalEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a super admin can create a realm with the full skeleton', function (): void {
    actingAsSuperAdmin();
    $uid = 'trealm'.bin2hex(random_bytes(3));

    Livewire::test(NewRealm::class)
        ->set('uid', $uid)
        ->set('name', 'Test Realm '.$uid)
        ->call('save')
        ->assertHasNoErrors();

    $community = Community::findByUid($uid);
    TestLdap::track($community); // hand the component-created community to teardown

    expect($community)->not->toBeNull()
        ->and(OrganizationalUnit::query()->find($community->peopleDn()))->not->toBeNull()
        ->and($community->adminsGroup())->not->toBeNull();
});

test('an admin can rename their realm', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    Livewire::test(EditRealm::class, ['realm' => $community])
        ->set('name', 'Renamed Realm')
        ->call('save')
        ->assertHasNoErrors();

    expect(Community::findByUid($uid)->getFirstAttribute('description'))->toBe('Renamed Realm');
});

test('an admin can store the address and contact details of their realm', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(EditRealm::class, ['realm' => $community])
        ->set('street', 'Carl-Zeiss-Straße')
        ->set('house_number', '3')
        ->set('postal_code', '07743')
        ->set('city', 'Jena')
        ->set('office_building', 'Haus auf der Mauer')
        ->set('office_room', '105')
        ->set('email', 'stura@example.test')
        ->set('phone', '03641 930990')
        ->set('website', 'https://stura.example.test/')
        ->set('bank_name', 'Sparkasse Jena')
        ->set('iban', 'de89 3704 0044 0532 0130 00')
        ->set('bic', 'heladef1jen')
        ->call('save')
        ->assertHasNoErrors();

    $contact = RealmContact::forRealm($community->getShortCode());
    expect($contact->streetLine())->toBe('Carl-Zeiss-Straße 3')
        ->and($contact->cityLine())->toBe('07743 Jena')
        ->and($contact->office_building)->toBe('Haus auf der Mauer')
        ->and($contact->office_room)->toBe('105')
        ->and($contact->websiteLabel())->toBe('stura.example.test')
        ->and($contact->iban)->toBe('DE89370400440532013000')
        ->and($contact->ibanLabel())->toBe('DE89 3704 0044 0532 0130 00')
        ->and($contact->bic)->toBe('HELADEF1JEN');

    Livewire::test(EditRealm::class, ['realm' => $community])
        ->assertSet('city', 'Jena')
        ->set('email', 'not-an-address')
        ->set('website', 'stura.example.test')
        ->set('iban', 'DE89 3704')
        ->set('bic', 'HELA')
        ->call('save')
        ->assertHasErrors(['email', 'website', 'iban', 'bic']);
});

test('an admin can create, edit and delete export templates', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    Livewire::test(EditMembershipExportTemplate::class, ['realm' => $community])
        ->set('name', 'Nachweis für das Landesamt')
        ->set('dateFormat', ExportDateFormat::MonthYear->value)
        ->set('withLetter', true)
        ->set('recipient.organisation', 'Landesamt')
        ->set('recipient.city', 'Erfurt')
        ->set('headerTitle', 'Studierendenrat')
        ->set('senderUnit', 'Sprecher:innen')
        ->set('senderEmail', 'sprecher@example.test')
        ->set('subject', 'Tätigkeit von [person.name]')
        ->set('letterBody', '<p>[person.name] war aktiv.</p><script>alert(1)</script>')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.membership-export', ['realm' => $uid]));

    $template = MembershipExportTemplate::where('realm', $uid)->sole();
    expect($template->with_letter)->toBeTrue()
        ->and($template->sender)->toBe(LetterSender::Realm)
        ->and($template->date_format)->toBe(ExportDateFormat::MonthYear)
        ->and($template->recipient)->toBe(['organisation' => 'Landesamt', 'city' => 'Erfurt'])
        ->and($template->header_title)->toBe('Studierendenrat')
        ->and($template->sender_email)->toBe('sprecher@example.test')
        ->and($template->subject)->toBe('Tätigkeit von [person.name]')
        ->and($template->body)->toBe('<p>[person.name] war aktiv.</p>');

    Livewire::test(EditMembershipExportTemplate::class, ['realm' => $community, 'template' => $template])
        ->assertSet('recipient.city', 'Erfurt')
        ->set('sender', LetterSender::User->value)
        ->set('senderEmail', 'not-an-address')
        ->set('letterBody', '<p>[person.shoe_size]</p>')
        ->call('save')
        ->assertHasErrors(['senderEmail', 'letterBody'])
        ->set('senderEmail', '')
        ->set('letterBody', '<p>Gruß [signature]</p>')
        ->set('subject', 'Betreff [signature]')
        ->call('save')
        ->assertHasErrors(['letterBody', 'subject'])
        ->set('subject', '')
        ->set('letterBody', '<p>Mit freundlichen Grüßen</p><p>[signature;name=[sender.name];function=Sprecher:in]</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($template->fresh()->sender)->toBe(LetterSender::User);

    Livewire::test(ListMembershipExportTemplates::class, ['realm' => $community])
        ->assertSee('Nachweis für das Landesamt')
        ->call('deletePrepare', $template->id)
        ->call('deleteCommit');

    expect(MembershipExportTemplate::where('realm', $uid)->exists())->toBeFalse();
});

test('the export template pages render', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $template = MembershipExportTemplate::create(['realm' => $uid, 'name' => 'Nachweis', 'with_letter' => true, 'sender' => LetterSender::User]);
    actingAsAdmin($community);

    $this->get(route('realms.membership-export', ['realm' => $uid]))->assertOk()->assertSee('Nachweis');
    $this->get(route('realms.membership-export.new', ['realm' => $uid]))->assertOk()->assertSee(__('realms.membership_export.new'));
    $this->get(route('realms.membership-export.edit', ['realm' => $uid, 'template' => $template->id]))
        ->assertOk()
        ->assertSee(__('realms.membership_export.sender_user_hint'))
        ->assertSee('[person.name]');
});

test('a template without a cover letter needs no text', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(EditMembershipExportTemplate::class, ['realm' => $community])
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertHasNoErrors(['letterBody'])
        ->set('name', 'Nur Übersicht')
        ->call('save')
        ->assertHasNoErrors();

    expect(MembershipExportTemplate::where('realm', $community->getShortCode())->sole()->with_letter)->toBeFalse();
});

test('a template of another realm cannot be edited', function (): void {
    $community = newCommunity();
    $other = newCommunity();
    $template = MembershipExportTemplate::create(['realm' => $other->getShortCode(), 'name' => 'Fremd']);
    actingAsAdmin($community);

    Livewire::test(EditMembershipExportTemplate::class, ['realm' => $community, 'template' => $template])
        ->assertNotFound();
});

test('a super admin can delete a realm once the shortcode is confirmed', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->call('deletePrepare', $uid)
        ->set('deleteConfirmText', $uid)
        ->call('deleteCommit');

    expect(Community::findByUid($uid))->toBeNull();
});

test('deleting a realm also purges its DB-side data', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    $member = TestLdap::member($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    RoleMembership::create([
        'realm' => $uid,
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today()->subMonth(),
    ]);
    GroupMailmanList::createForGroups(['realm' => $uid, 'mailman_list_id' => 'newsletter.lists.example.org'], [$group->getDn()]);
    $invitation = Invitation::create(['realm' => $uid, 'email' => 'invited@example.test', 'expires_at' => now()->addDays(7)]);
    UserAdditionalEmail::create(['username' => $member->username, 'realm' => $uid, 'address' => 'alias@example.test']);

    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('App', ['https://app.example.test/callback']);
    $client->forceFill(['community_uid' => $uid])->save();
    $token = Token::create([
        'id' => Str::random(80),
        'user_id' => $member->id,
        'client_id' => $client->id,
        'scopes' => [],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);
    RefreshToken::create(['id' => Str::random(80), 'access_token_id' => $token->id, 'revoked' => false, 'expires_at' => now()->addDay()]);
    DB::table('oauth_device_codes')->insert([
        'id' => Str::random(80),
        'user_id' => $member->id,
        'client_id' => $client->id,
        'user_code' => 'ABCD1234',
        'scopes' => '[]',
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
    RealmContact::create(['realm' => $uid, 'city' => 'Jena']);
    MembershipExportTemplate::create(['realm' => $uid, 'name' => 'Nachweis']);

    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->call('deletePrepare', $uid)
        ->set('deleteConfirmText', $uid)
        ->call('deleteCommit');

    expect(Community::findByUid($uid))->toBeNull()
        ->and(RoleMembership::where('realm', $uid)->exists())->toBeFalse()
        ->and(GroupMembership::where('group_dn', $group->getDn())->exists())->toBeFalse()
        ->and(GroupMailmanList::where('realm', $uid)->exists())->toBeFalse()
        ->and(Invitation::find($invitation->id))->toBeNull()
        ->and(DB::table('invitation_role_selections')->where('invitation_id', $invitation->id)->exists())->toBeFalse()
        ->and(UserAdditionalEmail::where('realm', $uid)->exists())->toBeFalse()
        ->and(User::find($member->id))->toBeNull()
        ->and(DB::table('oauth_clients')->where('id', $client->id)->exists())->toBeFalse()
        ->and(Token::find($token->id))->toBeNull()
        ->and(RefreshToken::where('access_token_id', $token->id)->exists())->toBeFalse()
        ->and(DB::table('oauth_device_codes')->where('client_id', $client->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $member->id)->exists())->toBeFalse()
        ->and(RealmContact::forRealm($uid))->toBeNull()
        ->and(MembershipExportTemplate::where('realm', $uid)->exists())->toBeFalse();
});

test('the admin realm cannot be deleted', function (): void {
    $superAdmin = actingAsSuperAdmin();
    $adminRealm = new Community(['ou' => Community::ADMIN_REALM_UID]);

    expect($superAdmin->can('delete', $adminRealm))->toBeFalse();
});

test('deleting a realm is rejected if the typed shortcode does not match', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->call('deletePrepare', $uid)
        ->set('deleteConfirmText', 'wrong-code')
        ->call('deleteCommit')
        ->assertHasErrors('deleteConfirmText');

    expect(Community::findByUid($uid))->not->toBeNull();
});

test('an admin can add a domain to their realm', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);
    $dc = 'dom'.bin2hex(random_bytes(3)).'.de';

    Livewire::test(NewDomain::class, ['realm' => $community])
        ->set('dc', $dc)
        ->call('save')
        ->assertHasNoErrors();

    expect(Domain::findBy('dc', $dc))->not->toBeNull();
});
