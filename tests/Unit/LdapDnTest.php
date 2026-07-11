<?php

use App\Ldap\Domain;

/**
 * Pure unit coverage (no framework boot, no directory access) for the LDAP
 * distinguished-name helpers.
 */
test('the domains dn root is built from the community uid', function (): void {
    expect(Domain::dnRoot('testcom'))
        ->toBe('ou=Domains,ou=testcom,ou=Communities,{base}');
});
