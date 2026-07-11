<?php

use App\Models\RoleMembership;

/**
 * RoleMembership::isActive() decides whether a (LDAP-mirrored) membership counts
 * as current based on its from/until window. These cases exercise that pure date
 * logic without touching the database.
 */
function membership(array $attributes): RoleMembership
{
    return (new RoleMembership)->forceFill($attributes);
}

test('a membership without an end date is always active', function (): void {
    expect(membership(['from' => today()->subYear(), 'until' => null])->isActive())->toBeTrue();
});

test('a membership is active inside its window', function (): void {
    expect(membership(['from' => today()->subDay(), 'until' => today()->addDay()])->isActive())->toBeTrue();
});

test('a membership that already ended is not active', function (): void {
    expect(membership(['from' => today()->subWeek(), 'until' => today()->subDay()])->isActive())->toBeFalse();
});

test('a membership that has not started yet is not active', function (): void {
    expect(membership(['from' => today()->addDay(), 'until' => today()->addWeek()])->isActive())->toBeFalse();
});
