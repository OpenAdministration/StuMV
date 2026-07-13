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
