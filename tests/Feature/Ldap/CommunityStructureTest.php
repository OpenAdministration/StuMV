<?php

use App\Ldap\Community;
use App\Ldap\Domain;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Communities, their groups and their domains live in LDAP (see the
 * architecture split documented in the README). These tests pin the shape of
 * the seeded directory the rest of the app relies on.
 */
test('a community can be looked up by its uid', function (): void {
    $community = Community::findByUid('testcom');

    expect($community)->not->toBeNull()
        ->and($community->getFirstAttribute('ou'))->toBe('testcom');
});

test('looking up an unknown community returns null', function (): void {
    expect(Community::findByUid('does-not-exist'))->toBeNull();
});

test('findOrFailByUid aborts with a 404 for an unknown community', function (): void {
    Community::findOrFailByUid('does-not-exist');
})->throws(NotFoundHttpException::class);

test('the members group exposes the community members', function (): void {
    $members = Community::findByUid('testcom')->membersGroup()->members()->get();

    $uids = $members->map(fn ($m) => $m->getFirstAttribute('uid'));

    expect($uids)->toContain('alice')->toContain('admin');
});

test('the admins group of the demo community contains its admin', function (): void {
    $admins = Community::findByUid('demo')->adminsGroup()->members()->get();

    $uids = $admins->map(fn ($m) => $m->getFirstAttribute('uid'));

    expect($uids)->toContain('demo-stura');
});

test('a domain resolves back to the community that owns it', function (): void {
    $domain = Domain::findByOrFail('dc', 'example.test');

    expect($domain->community()->getFirstAttribute('ou'))->toBe('testcom');
});
