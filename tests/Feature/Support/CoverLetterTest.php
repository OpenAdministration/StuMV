<?php

use App\Enums\ExportDateFormat;
use App\Enums\LetterRecipient;
use App\Enums\LetterSender;
use App\Ldap\Community;
use App\Ldap\User;
use App\Models\MembershipExportTemplate;
use App\Models\RealmContact;
use App\Support\CoverLetter;
use App\Support\LetterPlaceholders;
use App\Support\MembershipCertificatePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

function letterPerson(): User
{
    return new User(['uid' => 'mmuster', 'cn' => 'Max <Muster>', 'givenName' => 'Max', 'sn' => 'Muster', 'mail' => 'max@example.test']);
}

function letterSender(): User
{
    return new User([
        'cn' => 'Helen Würflein',
        'mail' => 'helen@example.test',
        'telephoneNumber' => '+493641930990',
        'street' => 'Lutherstraße 1',
        'postalCode' => '07743',
        'l' => 'Jena',
    ]);
}

test('a realm letter takes the realm details where the template leaves them open', function (): void {
    RealmContact::create(['realm' => 'stura', 'city' => 'Jena', 'email' => 'stura@example.test', 'phone' => '03641 930990', 'office_room' => '105']);
    $template = new MembershipExportTemplate([
        'realm' => 'stura',
        'sender' => LetterSender::Realm,
        'date_format' => ExportDateFormat::German,
        'recipient' => ['organisation' => 'Landesamt', 'city' => 'Erfurt'],
        'sender_phone' => '03641 930991',
        'subject' => 'Tätigkeit von [person.name]',
        'body' => '<p>[person.name] ([person.email]) war in [realm.name] aktiv, Stand [today].</p><p>Mit freundlichen Grüßen<br>[sender.name]</p>',
    ]);

    $letter = CoverLetter::fromTemplate($template, new Community(['ou' => 'stura', 'description' => 'StuRa Jena']), letterPerson(), letterSender());

    expect($letter->senderName)->toBe('StuRa Jena')
        ->and($letter->contact?->city)->toBe('Jena')
        ->and($letter->email)->toBe('stura@example.test')
        ->and($letter->phone)->toBe('03641 930991')
        ->and($letter->room)->toBe('105')
        ->and($letter->recipient)->toBe("Landesamt\nErfurt")
        ->and($letter->subject)->toBe('Tätigkeit von Max <Muster>')
        ->and($letter->body)->toBe('<p>Max &lt;Muster&gt; (max@example.test) war in StuRa Jena aktiv, Stand '.Date::today()->format('d.m.Y').'.</p><p>Mit freundlichen Grüßen<br />Helen Würflein</p>');
});

test('a letter sent by a person carries their profile details', function (): void {
    RealmContact::create(['realm' => 'stura', 'email' => 'stura@example.test', 'iban' => 'DE89370400440532013000']);
    $template = new MembershipExportTemplate([
        'sender' => LetterSender::User,
        'header_title' => 'Studierendenrat',
        'header_subtitle' => 'der FSU Jena',
        'sender_unit' => 'Sprecher:innen',
        'sender_email' => 'sprecher@example.test',
        'body' => '<p>Bescheinigung für [person.name]</p>',
    ]);

    $letter = CoverLetter::fromTemplate($template, new Community(['ou' => 'stura', 'description' => 'StuRa Jena']), letterPerson(), letterSender());

    expect($letter->senderName)->toBe('Helen Würflein')
        ->and($letter->headerTitle)->toBe('Helen Würflein')
        ->and($letter->headerSubtitle)->toBeNull()
        ->and($letter->senderUnit)->toBeNull()
        ->and($letter->email)->toBeNull()
        ->and($letter->phone)->toBeNull()
        ->and($letter->fromPerson)->toBeTrue()
        ->and($letter->logoPath)->toBeNull()
        ->and($letter->contact?->streetLine())->toBe('Lutherstraße 1')
        ->and($letter->contact?->cityLine())->toBe('07743 Jena')
        ->and($letter->contact?->iban)->toBeNull()
        // the value is escaped, the HTML around it kept
        ->and($letter->body)->toBe('<p>Bescheinigung für Max &lt;Muster&gt;</p>');

    $pdf = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Muster', null, [], $letter);
    expect($pdf)->not->toContain('/URI (mailto:')->not->toContain('/URI (tel:');
});

test('a letter to the member is addressed to their profile address', function (): void {
    $template = new MembershipExportTemplate([
        'sender' => LetterSender::User,
        'recipient_type' => LetterRecipient::Member,
        'recipient' => ['organisation' => 'Landesamt'],
        'body' => '<p>Hallo</p>',
    ]);
    $person = new User(['cn' => 'Max Muster', 'street' => 'Markt 5', 'postalCode' => '07743', 'l' => 'Jena']);

    $letter = CoverLetter::fromTemplate($template, new Community(['ou' => 'stura', 'description' => 'StuRa Jena']), $person, letterSender());

    expect($letter->recipient)->toBe("Max Muster\nMarkt 5\n07743 Jena");
});

test('unknown placeholders are reported and left as they are', function (): void {
    expect(LetterPlaceholders::unknown('[person.name] [person.username] [today] [foo]'))->toBe(['[person.username]', '[foo]'])
        ->and(LetterPlaceholders::replace('[person.shoe_size]', ['person.name' => 'Max'], html: true))->toBe('[person.shoe_size]');
});

test('dates are written in the format of the template', function (): void {
    app()->setLocale('de');
    $date = Date::create(2026, 9, 7);

    expect(ExportDateFormat::Iso->format($date))->toBe('2026-09-07')
        ->and(ExportDateFormat::German->format($date))->toBe('07.09.2026')
        ->and(ExportDateFormat::MonthYear->format($date))->toBe('Sept. 2026')
        ->and(ExportDateFormat::MonthYear->format('2026-03-01'))->toBe('März 2026');
});

test('the letter is dated to the day even when the template only shows month and year', function (): void {
    $template = new MembershipExportTemplate(['date_format' => ExportDateFormat::MonthYear, 'body' => '<p>Stand [today]</p>']);

    $letter = CoverLetter::fromTemplate($template, new Community(['ou' => 'stura', 'description' => 'StuRa Jena']), letterPerson(), letterSender());
    $pdf = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Muster', null, [], $letter, ExportDateFormat::MonthYear);

    expect($letter->body)->toBe('<p>Stand '.Date::today()->format('d.m.Y').'</p>')
        ->and(ExportDateFormat::Iso->forDay())->toBe(ExportDateFormat::Iso)
        ->and($pdf)->not->toBeEmpty();
});
