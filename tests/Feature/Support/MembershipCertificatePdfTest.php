<?php

use App\Support\MembershipCertificatePdf;

function fakeMembership(string $role, string $committee): array
{
    $committeeEntry = new class($committee)
    {
        public function __construct(private readonly string $description) {}

        public function getFirstAttribute(string $attribute): string
        {
            return $this->description;
        }
    };

    $roleEntry = new class($role, $committeeEntry)
    {
        public function __construct(private readonly string $description, private readonly object $committee) {}

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
