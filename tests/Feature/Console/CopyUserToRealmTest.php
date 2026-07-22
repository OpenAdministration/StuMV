<?php

use App\Ldap\User as LdapUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a user is copied into another realm as an independent clone', function (): void {
    $source = newCommunity();
    $target = newCommunity();
    $user = TestLdap::makeUser(null, $source);
    $uid = $user->getFirstAttribute('uid');

    $this->artisan('app:copy-user', [
        'uid' => $uid,
        'from' => $source->getShortCode(),
        'to' => $target->getShortCode(),
    ])->assertExitCode(0);

    $inSource = LdapUser::query()->in($source->peopleDn())->where('uid', '=', $uid)->first();
    $inTarget = LdapUser::query()->in($target->peopleDn())->where('uid', '=', $uid)->first();

    expect($inSource)->not->toBeNull()
        ->and($inTarget)->not->toBeNull()
        ->and($inTarget->getFirstAttribute('cn'))->toBe($user->getFirstAttribute('cn'))
        ->and($inTarget->getConvertedGuid())->not->toBe($inSource->getConvertedGuid());
});

test('a dry run makes no changes', function (): void {
    $source = newCommunity();
    $target = newCommunity();
    $user = TestLdap::makeUser(null, $source);
    $uid = $user->getFirstAttribute('uid');

    $this->artisan('app:copy-user', [
        'uid' => $uid,
        'from' => $source->getShortCode(),
        'to' => $target->getShortCode(),
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(LdapUser::query()->in($target->peopleDn())->where('uid', '=', $uid)->first())->toBeNull();
});

test('a stale entry already at the destination is overwritten', function (): void {
    $source = newCommunity();
    $target = newCommunity();
    $user = TestLdap::makeUser(null, $source);
    $uid = $user->getFirstAttribute('uid');

    $stale = new LdapUser([
        'uid' => $uid,
        'cn' => 'Stale Leftover',
        'sn' => 'Leftover',
        'givenName' => 'Stale',
        'mail' => $uid.'@stale.test',
        'userPassword' => '{ARGON2}'.password_hash('Aa1!'.bin2hex(random_bytes(6)), PASSWORD_ARGON2ID),
    ]);
    $stale->setDn('uid='.$uid.','.$target->peopleDn());
    $stale->save();
    TestLdap::track($stale);

    $this->artisan('app:copy-user', [
        'uid' => $uid,
        'from' => $source->getShortCode(),
        'to' => $target->getShortCode(),
    ])->assertExitCode(0);

    $inTarget = LdapUser::query()->in($target->peopleDn())->where('uid', '=', $uid)->first();

    expect($inTarget)->not->toBeNull()
        ->and($inTarget->getFirstAttribute('cn'))->toBe($user->getFirstAttribute('cn'));
});

test('it fails when the source realm does not exist', function (): void {
    $target = newCommunity();

    $this->artisan('app:copy-user', [
        'uid' => 'whoever',
        'from' => 'does-not-exist-'.bin2hex(random_bytes(3)),
        'to' => $target->getShortCode(),
    ])->assertExitCode(1);
});

test('it fails when the target realm does not exist', function (): void {
    $source = newCommunity();
    $user = TestLdap::makeUser(null, $source);

    $this->artisan('app:copy-user', [
        'uid' => $user->getFirstAttribute('uid'),
        'from' => $source->getShortCode(),
        'to' => 'does-not-exist-'.bin2hex(random_bytes(3)),
    ])->assertExitCode(1);
});

test('it fails when the user does not exist in the source realm', function (): void {
    $source = newCommunity();
    $target = newCommunity();

    $this->artisan('app:copy-user', [
        'uid' => 'nobody-'.bin2hex(random_bytes(3)),
        'from' => $source->getShortCode(),
        'to' => $target->getShortCode(),
    ])->assertExitCode(1);
});

test('it fails when copying a user into the realm they are already in', function (): void {
    $community = newCommunity();
    $user = TestLdap::makeUser(null, $community);

    $this->artisan('app:copy-user', [
        'uid' => $user->getFirstAttribute('uid'),
        'from' => $community->getShortCode(),
        'to' => $community->getShortCode(),
    ])->assertExitCode(1);
});
