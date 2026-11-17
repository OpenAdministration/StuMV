<?php

use App\Models\RealmContact;
use App\Support\CoverLetter;
use App\Support\LetterPlaceholders;
use App\Support\MembershipCertificatePdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function fakeMembership(string $role, string $committee): array
{
    $committeeEntry = new readonly class($committee)
    {
        public function __construct(private string $description) {}

        public function getFirstAttribute(string $attribute): string
        {
            return $this->description;
        }
    };

    $roleEntry = new readonly class($role, $committeeEntry)
    {
        public function __construct(private string $description, private object $committee) {}

        public function getFirstAttribute(string $attribute): string
        {
            return $this->description;
        }

        public function committee(): object
        {
            return $this->committee;
        }
    };

    return ['role' => $roleEntry, 'from' => '2019-02-07', 'until' => ''];
}

test('a table spanning several pages repeats its head but tags it only once', function (): void {
    $memberships = array_map(fn (int $i): array => fakeMembership('Rolle '.$i, 'Gremium '.$i), range(1, 80));

    $pdf = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, $memberships);

    preg_match('#/Type /Pages .*?/Count (\d+)#s', $pdf, $pages);

    expect((int) $pages[1])->toBeGreaterThan(1)
        ->and(substr_count($pdf, '/S /TH'))->toBe(4)
        ->and(substr_count($pdf, '/S /TR'))->toBe(81);
});

test('a certificate without memberships still has a table head', function (): void {
    $pdf = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, []);

    expect(substr_count($pdf, '/S /TH'))->toBe(4)
        ->and($pdf)->not->toContain('/S /TD');
});

test('a cover letter is put on its own page in front of the certificate', function (): void {
    $letter = new CoverLetter(
        'Studierendenrat',
        new RealmContact(['street' => 'Carl-Zeiss-Straße', 'house_number' => '3', 'postal_code' => '07743', 'city' => 'Jena', 'office_building' => 'Haus auf der Mauer', 'office_room' => '105', 'email' => 'stura@example.test', 'phone' => '03641 930990', 'website' => 'https://stura.example.test', 'bank_name' => 'Sparkasse Jena', 'iban' => 'DE89370400440532013000', 'bic' => 'HELADEF1JEN']),
        "Thüringer Landtag\n99096 Erfurt",
        '<p>Sehr geehrte Damen und Herren,</p><p>anbei die <em>Übersicht</em>:</p><ul><li>Gremium</li></ul><p>Mit freundlichen Grüßen</p><script>alert(1)</script>',
        senderUnit: 'Sprecher:innen',
        building: 'Haus auf der Mauer',
        room: '105',
        email: 'sprecher@example.test',
        phone: '03641 930990',
        website: 'https://sprecher.example.test',
        subject: 'Tätigkeitsbescheinigung',
        headerTitle: 'Studierendenrat',
        headerSubtitle: 'der Friedrich-Schiller-Universität Jena',
    );

    $without = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, [fakeMembership('Rolle', 'Gremium')]);
    $with = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, [fakeMembership('Rolle', 'Gremium')], $letter);

    preg_match('#/Type /Pages .*?/Count (\d+)#s', $with, $pages);

    // title, subtitle, return address, recipient, unit and four contact entries, date, subject and three paragraphs
    expect((int) $pages[1])->toBe(2)
        ->and(substr_count($with, '/S /P') - substr_count($without, '/S /P'))->toBe(14)
        ->and(substr_count($with, '/S /H1'))->toBe(1)
        ->and(substr_count($with, '/S /TH'))->toBe(4)
        ->and(substr_count($with, '/S /LI'))->toBe(1)
        // phone, email and website in the info block and again in the footer
        ->and(substr_count($with, '/S /Link'))->toBe(6)
        ->and($with)->toContain('/URI (tel:03641930990)', '/URI (mailto:sprecher@example.test)', '/URI (https://sprecher.example.test)', '/URI (https://stura.example.test)');
});

test('an svg logo is embedded as vector graphic, its outline kept behind the fill', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100"><path d="M10 10 H190 V90 H10 Z" style="fill:#18181b;stroke:#ffffff;stroke-width:8;paint-order:stroke markers fill"/></svg>');
    $letter = new CoverLetter('Studierendenrat', null, 'Landtag', '<p>Text</p>', logoPath: 'logo.svg');

    $pdf = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, [], $letter);

    expect($pdf)->not->toContain('/Subtype /Image');
});

test('the letter body keeps emphasis and lists and drops everything else', function (): void {
    expect(CoverLetter::sanitizeBody('<p onclick="x"><strong>a</strong><a href="https://x">b</a></p><script>c</script><ul><li>d</li></ul>'))
        ->toBe('<p><strong>a</strong>b</p><ul><li>d</li></ul>')
        ->and(CoverLetter::bodyIsEmpty('<p>&nbsp;</p>'))->toBeTrue()
        ->and(CoverLetter::bodyIsEmpty('<p>x</p>'))->toBeFalse()
        ->and(CoverLetter::bodyIsEmpty('<p></p>'))->toBeTrue();
});

test('hyphenation patterns of the locale are loaded once and cached', function (): void {
    app()->setLocale('de');
    $key = 'pdf.hyphenation.de.'.filemtime(resource_path('hyphenation/hyph-de-1996.tex'));
    Cache::forget($key);

    (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, [fakeMembership('Rolle', 'Fachschaftsratskoordinationsausschuss')]);

    expect(Cache::get($key))->toBeArray()->not->toBeEmpty();
});

test('a locale without patterns still renders', function (): void {
    app()->setLocale('fr');

    expect((new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, []))->toStartWith('%PDF-');
});

test('a signature placeholder becomes room to sign instead of a paragraph', function (): void {
    $render = fn (string $body): string => (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, [], new CoverLetter('Studierendenrat', null, 'Landtag', $body));

    $plain = $render('<p>Mit freundlichen Grüßen</p><p>Helen Würflein</p>');
    $signed = $render('<p>Mit freundlichen Grüßen</p><p>[signature]</p><p>Helen Würflein</p>');
    $named = $render('<p>Mit freundlichen Grüßen</p><p>[signature;function=Sprecherin;name=Helen Würflein]</p>');

    expect(substr_count($signed, '/S /P'))->toBe(substr_count($plain, '/S /P'))
        ->and(substr_count($named, '/S /P'))->toBe(substr_count($plain, '/S /P') + 1)
        ->and(LetterPlaceholders::splitAtSignatures('<p>a</p><p> [signature] </p><p>b</p>'))->toBe([['<p>a</p>', []], ['<p>b</p>', null]])
        ->and(LetterPlaceholders::splitAtSignatures('<p>[signature;function=Sprecher:in;name=Max &amp; Moritz]</p>'))->toBe([['', ['Max & Moritz', 'Sprecher:in']], ['', null]])
        ->and(LetterPlaceholders::signatureMisplaced('<p>Gruß [signature]</p>'))->toBeTrue()
        ->and(LetterPlaceholders::signatureMisplaced('<p>[signature;rank=1]</p>'))->toBeTrue()
        ->and(LetterPlaceholders::signatureMisplaced('<p>[signature;name=[sender.name];function=Sprecher:in]</p>'))->toBeFalse()
        ->and(LetterPlaceholders::signatureMisplaced('<p>Gruß</p><p>[signature]</p>'))->toBeFalse();
});
