<?php

use App\Rules\DomainRegistrationRule;
use App\Rules\UniqueDomain;
use App\Rules\UniqueEmail;
use App\Rules\UserIsMember;
use Illuminate\Support\Facades\Validator;

/**
 * The custom validation rules resolve their answers from LDAP. These run against
 * the seeded directory (docker/openldap/bootstrap): the "testcom" community owns
 * the registerable domain example.test with member admin, while the
 * "demo" community's members are the demo-* users.
 */
function passesRule(string $attribute, mixed $value, object $rule): bool
{
    return Validator::make([$attribute => $value], [$attribute => [$rule]])->passes();
}

describe('DomainRegistrationRule', function (): void {
    test('accepts a domain that exists in LDAP', function (): void {
        expect(passesRule('domain', 'example.test', new DomainRegistrationRule))->toBeTrue();
    });

    test('rejects a domain that is not registerable', function (): void {
        expect(passesRule('domain', 'no-such-domain.invalid', new DomainRegistrationRule))->toBeFalse();
    });
});

describe('UniqueDomain', function (): void {
    test('rejects a domain that already exists', function (): void {
        expect(passesRule('dc', 'example.test', new UniqueDomain))->toBeFalse();
    });

    test('accepts a brand new domain', function (): void {
        expect(passesRule('dc', 'brand-new-'.uniqid().'.test', new UniqueDomain))->toBeTrue();
    });
});

describe('UniqueEmail', function (): void {
    test('rejects an address already in the directory', function (): void {
        expect(passesRule('email', 'admin@stumv.de', new UniqueEmail))->toBeFalse();
    });

    test('accepts an unused address', function (): void {
        expect(passesRule('email', 'nobody-'.uniqid().'@stumv.de', new UniqueEmail))->toBeTrue();
    });
});

describe('UserIsMember', function (): void {
    test('accepts a member of the community', function (): void {
        expect(passesRule('user', 'admin', new UserIsMember('testcom')))->toBeTrue();
    });

    test('rejects a user who is not a member of the community', function (): void {
        // demo-hhv belongs to the "demo" community, not "testcom".
        expect(passesRule('user', 'demo-hhv', new UserIsMember('testcom')))->toBeFalse();
    });

    test('rejects a username that does not exist at all', function (): void {
        expect(passesRule('user', 'ghost-'.uniqid(), new UserIsMember('testcom')))->toBeFalse();
    });
});
