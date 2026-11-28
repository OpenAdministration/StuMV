<?php

namespace Database\Seeders;

use App\Enums\ExportDateFormat;
use App\Enums\LetterRecipient;
use App\Enums\LetterSender;
use App\Models\GroupMembership;
use App\Models\Invitation;
use App\Models\InvitationRoleSelection;
use App\Models\MembershipExportTemplate;
use App\Models\PassportClient;
use App\Models\RealmContact;
use App\Models\RoleMembership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Laravel\Passport\ClientRepository;

/**
 * Fictional content for the "demo" realm, used for the user manual screenshots
 * (docs/screenshots). Run after DemoUsersSeeder; safe to run repeatedly.
 */
class DemoDocsSeeder extends Seeder
{
    private const string COMMITTEES = 'ou=Committees,ou=demo,ou=Communities,dc=stumv,dc=de';

    private const string GROUPS = 'ou=Groups,ou=demo,ou=Communities,dc=stumv,dc=de';

    public function run(): void
    {
        $stura = 'ou=StuRa,'.self::COMMITTEES;
        $finanzen = 'ou=Referat Finanzen,'.$stura;
        $fsr = 'ou=FSR,'.self::COMMITTEES;

        // [committee, role, username, from, until]
        $memberships = [
            [$stura, 'mitglied', 'demo-stura', '2025-10-01', null],
            [$stura, 'mitglied', 'demo-hhv', '2025-10-01', null],
            [$stura, 'mitglied', 'demo-kv', '2025-10-01', null],
            [$stura, 'mitglied', 'demo-hhv', '2024-04-01', '2025-03-31'],
            [$finanzen, 'haushaltsverantwortliche', 'demo-hhv', '2025-10-01', null],
            [$finanzen, 'kassenverantwortliche', 'demo-kv', '2025-10-01', null],
            [$finanzen, 'revision', 'demo-revision', '2025-10-01', '2026-09-30'],
            [$fsr, 'mitglied', 'demo-fsr', '2025-10-01', null],
            [$fsr, 'mitglied', 'demo-studi', '2026-04-01', null],
            [$fsr, 'mitglied', 'demo-stura', '2023-10-01', '2025-09-30'],
        ];

        foreach ($memberships as [$committeeDn, $roleCn, $username, $from, $until]) {
            // updateOrCreate: DemoUsersSeeder may have created the row with its own comment
            RoleMembership::updateOrCreate(
                ['committee_dn' => $committeeDn, 'role_cn' => $roleCn, 'username' => $username, 'from' => Date::parse($from)],
                ['realm' => 'demo', 'until' => $until ? Date::parse($until) : null, 'decided' => Date::parse($from)->subWeeks(2), 'comment' => 'Wahl in der konstituierenden Sitzung']
            );
        }

        $groupRoles = [
            'ref-finanzen' => [[$finanzen, 'haushaltsverantwortliche'], [$finanzen, 'kassenverantwortliche'], [$finanzen, 'revision']],
            'ref-finanzen-hhv' => [[$finanzen, 'haushaltsverantwortliche']],
            'ref-finanzen-kv' => [[$finanzen, 'kassenverantwortliche']],
            'ref-finanzen-belege' => [[$finanzen, 'haushaltsverantwortliche'], [$finanzen, 'kassenverantwortliche']],
        ];

        foreach ($groupRoles as $group => $roles) {
            foreach ($roles as [$committeeDn, $roleCn]) {
                GroupMembership::firstOrCreate(['group_dn' => "cn=$group,".self::GROUPS, 'role_dn' => "cn=$roleCn,$committeeDn"]);
            }
        }

        RealmContact::updateOrCreate(['realm' => 'demo'], [
            'street' => 'Musterstraße',
            'house_number' => '1',
            'postal_code' => '12345',
            'city' => 'Musterstadt',
            'office_building' => 'Studierendenhaus',
            'office_room' => '101',
            'email' => 'stura@demo.stumv.de',
            'phone' => '+49 123 456789',
            'website' => 'https://demo.stumv.de',
            'bank_name' => 'Musterbank',
            'iban' => 'DE02120300000000202051',
            'bic' => 'BYLADEM1001',
        ]);

        MembershipExportTemplate::updateOrCreate(['realm' => 'demo', 'name' => 'Flexsemester-Antrag'], [
            'with_letter' => true,
            'sender' => LetterSender::Realm,
            'date_format' => ExportDateFormat::German,
            'recipient_type' => LetterRecipient::Other,
            'recipient' => ['organisation' => 'Hochschule Musterstadt', 'unit' => 'Prüfungsamt', 'street' => 'Hochschulring', 'house_number' => '5', 'postal_code' => '12345', 'city' => 'Musterstadt'],
            'header_title' => 'Studierendenrat',
            'header_subtitle' => 'der Hochschule Musterstadt',
            'sender_unit' => 'Sprecher:innen',
            'subject' => 'Bescheinigung über die Gremientätigkeit von [person.name]',
            'body' => '<p>Sehr geehrte Damen und Herren,</p><p>hiermit bestätigen wir, dass [person.name] sich in den unten aufgeführten Gremien der [realm.name] engagiert hat.</p><p>Mit freundlichen Grüßen</p><p>[signature;name=[sender.name];function=Sprecher:in]</p>',
        ]);

        $clients = resolve(ClientRepository::class);
        if (! PassportClient::where('community_uid', 'demo')->where('name', 'Wiki')->exists()) {
            $clients->createAuthorizationCodeGrantClient('Wiki', ['https://wiki.demo.stumv.de/oauth/callback'])
                ->forceFill(['community_uid' => 'demo', 'scopes' => ['openid', 'profile', 'email', 'groups']])->save();
        }
        if (! PassportClient::where('community_uid', 'demo')->where('name', 'StuFiS')->exists()) {
            $clients->createClientCredentialsGrantClient('StuFiS')
                ->forceFill(['community_uid' => 'demo', 'scopes' => ['committees', 'groups']])->save();
        }

        $invitation = Invitation::firstOrCreate(
            ['realm' => 'demo', 'email' => 'neu@example.org', 'accepted_at' => null],
            ['invited_by_username' => 'demo-hhv', 'expires_at' => now()->addDays(7)]
        );
        InvitationRoleSelection::firstOrCreate(['invitation_id' => $invitation->id, 'committee_dn' => $fsr, 'role_cn' => 'mitglied']);
    }
}
