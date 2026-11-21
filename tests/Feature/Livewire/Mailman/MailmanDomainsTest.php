<?php

use App\Ldap\Community;
use App\Livewire\Mailman\MailmanDomains;
use App\Models\MailmanDomainRealm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.mailman.api_url' => 'http://mailman.test/3.1',
        'services.mailman.list_creation' => true,
    ]);
    Http::fake(['mailman.test/3.1/domains' => Http::response(['entries' => [['mail_host' => 'lists.example.org'], ['mail_host' => 'lists.other.org']]])]);
});

test('super admins can allow mailman domains per realm', function (): void {
    $kept = newCommunity();
    $added = newCommunity();
    $removed = newCommunity();
    MailmanDomainRealm::create(['mail_host' => 'lists.example.org', 'realm' => $kept->getShortCode()]);
    MailmanDomainRealm::create(['mail_host' => 'lists.example.org', 'realm' => $removed->getShortCode()]);
    actingAsSuperAdmin();

    Livewire::test(MailmanDomains::class, ['realm' => Community::findOrFailByUid(Community::ADMIN_REALM_UID)])
        ->assertSet('allowed.0.mail_host', 'lists.example.org')
        ->assertSet('allowed.0.realms', [$kept->getShortCode(), $removed->getShortCode()])
        ->assertSet('allowed.1.realms', [])
        ->set('allowed.0.realms', [$kept->getShortCode(), $added->getShortCode()])
        ->set('allowed.1.realms', [$added->getShortCode()])
        ->call('save')
        ->assertHasNoErrors();

    expect(MailmanDomainRealm::allowedFor($kept->getShortCode()))->toBe(['lists.example.org'])
        ->and(MailmanDomainRealm::allowedFor($added->getShortCode()))->toBe(['lists.example.org', 'lists.other.org'])
        ->and(MailmanDomainRealm::allowedFor($removed->getShortCode()))->toBe([]);
});

test('unknown realms are rejected', function (): void {
    actingAsSuperAdmin();

    Livewire::test(MailmanDomains::class, ['realm' => Community::findOrFailByUid(Community::ADMIN_REALM_UID)])
        ->set('allowed.0.realms', ['nope'])
        ->call('save')
        ->assertHasErrors(['allowed.0.realms.0']);

    expect(MailmanDomainRealm::count())->toBe(0);
});

test('the page only exists in the admin realm', function (): void {
    $community = newCommunity();
    actingAsSuperAdmin();

    $this->get(route('realms.mailman-domains', ['realm' => $community->getShortCode()]))->assertNotFound();
});

test('the page does not exist while list creation is off', function (): void {
    config(['services.mailman.list_creation' => false]);
    actingAsSuperAdmin();

    $this->get(route('realms.mailman-domains', ['realm' => Community::ADMIN_REALM_UID]))->assertNotFound();
});

test('community admins cannot open the page', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $this->get(route('realms.mailman-domains', ['realm' => Community::ADMIN_REALM_UID]))->assertForbidden();
});
