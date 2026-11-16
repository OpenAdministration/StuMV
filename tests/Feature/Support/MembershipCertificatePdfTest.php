<?php

use App\Models\RealmContact;
use App\Support\CoverLetter;
use App\Support\MembershipCertificatePdf;

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
        new RealmContact(['street' => 'Carl-Zeiss-Straße', 'house_number' => '3', 'postal_code' => '07743', 'city' => 'Jena', 'email' => 'stura@example.test', 'phone' => '03641 930990', 'website' => 'https://stura.example.test']),
        "Thüringer Landtag\n99096 Erfurt",
        '<p>Sehr geehrte Damen und Herren,</p><p>anbei die <em>Übersicht</em>:</p><ul><li>Gremium</li></ul><p>Mit freundlichen Grüßen</p><script>alert(1)</script>',
        senderUnit: 'Sprecher:innen',
        email: 'sprecher@example.test',
        signerName: 'Helen Würflein',
        signerFunction: 'Sprecherin',
        subject: 'Tätigkeitsbescheinigung',
    );

    $without = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, [fakeMembership('Rolle', 'Gremium')]);
    $with = (new MembershipCertificatePdf)->render('Tätigkeiten', 'Max Mustermann', null, [fakeMembership('Rolle', 'Gremium')], $letter);

    preg_match('#/Type /Pages .*?/Count (\d+)#s', $with, $pages);

    // sender, return address, recipient, unit and four contact entries, date, subject, three paragraphs, closing, name and position
    expect((int) $pages[1])->toBe(2)
        ->and(substr_count($with, '/S /P') - substr_count($without, '/S /P'))->toBe(16)
        ->and(substr_count($with, '/S /H1'))->toBe(1)
        ->and(substr_count($with, '/S /TH'))->toBe(4)
        ->and(substr_count($with, '/S /LI'))->toBe(1);
});

test('the letter body keeps emphasis and lists and drops everything else', function (): void {
    expect(CoverLetter::sanitizeBody('<p onclick="x"><strong>a</strong><a href="https://x">b</a></p><script>c</script><ul><li>d</li></ul>'))
        ->toBe('<p><strong>a</strong>b</p><ul><li>d</li></ul>')
        ->and(CoverLetter::bodyIsEmpty('<p>&nbsp;</p>'))->toBeTrue()
        ->and(CoverLetter::bodyIsEmpty('<p>x</p>'))->toBeFalse()
        ->and(CoverLetter::bodyIsEmpty('<p></p>'))->toBeTrue();
});
