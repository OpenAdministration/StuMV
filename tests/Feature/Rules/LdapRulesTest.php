<?php

use App\Ldap\Community;
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
    // Realm-bound now (registration is chosen via {realm}/register) - the
    // domain must belong to that specific community, not just any community.
    test('accepts a domain that exists in this realm', function (): void {
        expect(passesRule('domain', 'example.test', new DomainRegistrationRule(Community::findByUid('testcom'))))->toBeTrue();
    });

    test('rejects a domain that is not registerable at all', function (): void {
        expect(passesRule('domain', 'no-such-domain.invalid', new DomainRegistrationRule(Community::findByUid('testcom'))))->toBeFalse();
    });

    test('rejects a domain that belongs to a different realm', function (): void {
        expect(passesRule('domain', 'example.test', new DomainRegistrationRule(Community::findByUid('demo'))))->toBeFalse();
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
    // Realm-scoped now - the same address is explicitly allowed to exist in
    // more than one realm (e.g. the "admin" dual-account case), so the rule
    // needs a realm to check against; without one it always passes.
    test('rejects an address already in the directory', function (): void {
        expect(passesRule('email', 'admin@stumv.de', new UniqueEmail(Community::findByUid('testcom'))))->toBeFalse();
    });

    test('accepts an unused address', function (): void {
        expect(passesRule('email', 'nobody-'.uniqid().'@stumv.de', new UniqueEmail(Community::findByUid('testcom'))))->toBeTrue();
    });

    test('passes with no realm to check against', function (): void {
        expect(passesRule('email', 'admin@stumv.de', new UniqueEmail))->toBeTrue();
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
