<?php

use App\Ldap\Domain;
use App\Livewire\Tools\UsersNotInUniLdap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Connection;
use LdapRecord\Container;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['ldap.connections.uni.base_dn' => 'ou=People,dc=stumv,dc=de']);

    Container::addConnection(new Connection([
        'hosts' => ['127.0.0.1'],
        'port' => 13389,
        'base_dn' => 'ou=People,dc=stumv,dc=de',
    ]), 'uni');
});

test('a member missing from the university LDAP is listed without crashing', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $domain = new Domain(['dc' => 'example.test']);
    $domain->setDn('dc=example.test,'.Domain::dnRoot($uid));
    $domain->save();

    $member = TestLdap::member($community);
    actingAsModerator($community);

    Livewire::test(UsersNotInUniLdap::class, ['uid' => $community])
        ->call('searchForUsersNotInUniLdap')
        ->assertSet('comparisonCompleted', true)
        ->assertSee($member->username);
});

test('checking members against the university LDAP uses a single batched query, not one per member', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $domain = new Domain(['dc' => 'example.test']);
    $domain->setDn('dc=example.test,'.Domain::dnRoot($uid));
    $domain->save();

    TestLdap::member($community);
    TestLdap::member($community);
    TestLdap::member($community);
    actingAsModerator($community);

    $mailQueries = [];
    Container::getInstance()->getDispatcher()->listen('LdapRecord\Query\Events\*', function ($eventName, $events) use (&$mailQueries): void {
        foreach ($events as $event) {
            $query = $event->getQuery()->getUnescapedQuery();
            if (str_contains($query, 'mail=')) {
                $mailQueries[] = $query;
            }
        }
    });

    Livewire::test(UsersNotInUniLdap::class, ['uid' => $community])
        ->call('searchForUsersNotInUniLdap');

    expect($mailQueries)->toHaveCount(1);
});

test('checking more than 10 members batches the uni LDAP lookup in groups of 10', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $domain = new Domain(['dc' => 'example.test']);
    $domain->setDn('dc=example.test,'.Domain::dnRoot($uid));
    $domain->save();

    foreach (range(1, 15) as $i) {
        TestLdap::member($community);
    }
    actingAsModerator($community);

    $mailQueries = [];
    Container::getInstance()->getDispatcher()->listen('LdapRecord\Query\Events\*', function ($eventName, $events) use (&$mailQueries): void {
        foreach ($events as $event) {
            $query = $event->getQuery()->getUnescapedQuery();
            if (str_contains($query, 'mail=')) {
                $mailQueries[] = $query;
            }
        }
    });

    Livewire::test(UsersNotInUniLdap::class, ['uid' => $community])
        ->call('searchForUsersNotInUniLdap');

    expect($mailQueries)->toHaveCount(2);
    foreach ($mailQueries as $query) {
        expect(substr_count($query, 'mail='))->toBeLessThanOrEqual(10);
    }
});
