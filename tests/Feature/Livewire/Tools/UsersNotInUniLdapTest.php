<?php

use App\Ldap\Domain;
use App\Ldap\User as LdapUser;
use App\Livewire\Tools\UsersNotInUniLdap;
use App\Models\ProfilePicture;
use App\Models\User as DbUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

test('the page 404s when the uni LDAP connection has no base_dn configured', function (): void {
    config(['ldap.connections.uni.base_dn' => null]);
    $community = newCommunity();
    actingAsModerator($community);

    Livewire::test(UsersNotInUniLdap::class, ['uid' => $community])
        ->assertStatus(404);
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
        expect(substr_count((string) $query, 'mail='))->toBeLessThanOrEqual(10);
    }
});

test('the uni LDAP batch size is configurable via ldap.uni_batch_size', function (): void {
    config(['ldap.uni_batch_size' => 5]);

    $community = newCommunity();
    $uid = $community->getShortCode();

    $domain = new Domain(['dc' => 'example.test']);
    $domain->setDn('dc=example.test,'.Domain::dnRoot($uid));
    $domain->save();

    foreach (range(1, 12) as $i) {
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

    // 12 candidates at a batch size of 5 -> 3 batches (5, 5, 2).
    expect($mailQueries)->toHaveCount(3);
    foreach ($mailQueries as $query) {
        expect(substr_count((string) $query, 'mail='))->toBeLessThanOrEqual(5);
    }
});

test('results are sorted by name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $domain = new Domain(['dc' => 'example.test']);
    $domain->setDn('dc=example.test,'.Domain::dnRoot($uid));
    $domain->save();

    $zebra = TestLdap::makeUser();
    $zebra->fill(['cn' => 'Zebra'])->save();
    TestLdap::attach($community->membersGroup(), $zebra);

    $apple = TestLdap::makeUser();
    $apple->fill(['cn' => 'Apple'])->save();
    TestLdap::attach($community->membersGroup(), $apple);

    $mango = TestLdap::makeUser();
    $mango->fill(['cn' => 'Mango'])->save();
    TestLdap::attach($community->membersGroup(), $mango);

    actingAsModerator($community);

    $html = Livewire::test(UsersNotInUniLdap::class, ['uid' => $community])
        ->call('searchForUsersNotInUniLdap')
        ->html();

    $posApple = strpos($html, 'Apple');
    $posMango = strpos($html, 'Mango');
    $posZebra = strpos($html, 'Zebra');

    expect($posApple)->toBeLessThan($posMango)
        ->and($posMango)->toBeLessThan($posZebra);
});

test('the search field filters results by name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $domain = new Domain(['dc' => 'example.test']);
    $domain->setDn('dc=example.test,'.Domain::dnRoot($uid));
    $domain->save();

    $apple = TestLdap::makeUser();
    $apple->fill(['cn' => 'Apple'])->save();
    TestLdap::attach($community->membersGroup(), $apple);

    $mango = TestLdap::makeUser();
    $mango->fill(['cn' => 'Mango'])->save();
    TestLdap::attach($community->membersGroup(), $mango);

    actingAsModerator($community);

    Livewire::test(UsersNotInUniLdap::class, ['uid' => $community])
        ->call('searchForUsersNotInUniLdap')
        ->assertSee('Apple')
        ->assertSee('Mango')
        ->set('search', 'App')
        ->assertSee('Apple')
        ->assertDontSee('Mango');
});

test('deleting a user removes the LDAP entry, database rows, and profile picture', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $member = TestLdap::member($community);
    actingAsSuperAdmin();

    Storage::disk('public')->put('avatars/some-file-id.jpg', 'fake-image-contents');
    ProfilePicture::create([
        'user' => $member->username,
        'file_id' => 'some-file-id',
    ]);

    Livewire::test(UsersNotInUniLdap::class, ['uid' => $community])
        ->set('userToDelete', $member->username)
        ->call('deleteUser');

    expect(LdapUser::findByUsername($member->username))->toBeNull()
        ->and(DbUser::where('username', $member->username)->exists())->toBeFalse()
        ->and(ProfilePicture::where('user', $member->username)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('avatars/some-file-id.jpg');
});

test('deleting a user without a profile picture does not error', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    actingAsSuperAdmin();

    Livewire::test(UsersNotInUniLdap::class, ['uid' => $community])
        ->set('userToDelete', $member->username)
        ->call('deleteUser')
        ->assertOk();

    expect(LdapUser::findByUsername($member->username))->toBeNull();
});
