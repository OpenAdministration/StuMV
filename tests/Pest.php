<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full Laravel application (via Tests\TestCase) so they
| can hit routes, Livewire components, the database and the dockerised LDAP.
| Unit tests stay on the plain PHPUnit test case: they exercise pure logic
| (model configuration, value objects) without booting the framework.
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidUuid', fn () => $this->toMatch('/^[0-9a-f-]{36}$/i'));
