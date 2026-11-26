<?php

use App\Ldap\Community;
use App\Livewire\Mailman\ListGroupMailmanLists;
use App\Livewire\Mailman\MailmanDomains;
use App\Livewire\Mailman\NewGroupMailmanList;
use App\Livewire\Realm\ListRealms;
use App\Livewire\Realm\NewRealm;
use App\Models\ActivityLog;
use App\Models\GroupMailmanList;
use App\Models\MailmanDomainRealm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Community::findByUid('logtest')?->delete(recursive: true);
});

test('a mailing list mapping is logged with its groups when created, changed, switched off and deleted', function (): void {
    $community = newCommunity();
    $newsletter = TestLdap::makeGroup($community, 'newsletter');
    $board = TestLdap::makeGroup($community, 'board');
    actingAsAdmin($community);

    Livewire::test(NewGroupMailmanList::class, ['realm' => $community])
        ->set('member_groups', ['newsletter'])
        ->set('mailman_list_id', 'news.lists.example.org')
        ->call('save')
        ->assertHasNoErrors();
    $mapping = GroupMailmanList::sole();
    Livewire::test(NewGroupMailmanList::class, ['realm' => $community, 'listId' => 'news.lists.example.org'])
        ->set('moderator_groups', ['board'])
        ->call('save')
        ->assertHasNoErrors();
    Livewire::test(ListGroupMailmanLists::class, ['realm' => $community])
        ->call('toggleEnabled', $mapping->id)
        ->call('deletePrepare', $mapping->id)
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['mailing_list.created', 'mailing_list.updated', 'mailing_list.updated', 'mailing_list.deleted'])
        ->and($logs->pluck('subject_label')->unique()->all())->toBe(['news.lists.example.org'])
        ->and($logs[0]->details)->toBe([
            'mailman_list_id' => 'news.lists.example.org',
            'enabled' => true,
            'member_groups' => [$newsletter->getDn()],
            'moderator_groups' => [],
        ])
        ->and($logs[1]->details)->toBe(['moderator_groups' => ['old' => [], 'new' => [$board->getDn()]]])
        ->and($logs[2]->details)->toBe(['enabled' => ['old' => true, 'new' => false]])
        ->and($logs[3]->details['member_groups'])->toBe([$newsletter->getDn()]);
});

test('changing which realms may use a mailman domain is logged per changed domain', function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1', 'services.mailman.list_creation' => true]);
    Http::fake(['mailman.test/3.1/domains' => Http::response(['entries' => [['mail_host' => 'lists.example.org'], ['mail_host' => 'lists.other.org']]])]);
    $community = newCommunity();
    MailmanDomainRealm::create(['mail_host' => 'lists.other.org', 'realm' => $community->getShortCode()]);
    actingAsSuperAdmin();

    Livewire::test(MailmanDomains::class, ['realm' => Community::findOrFailByUid(Community::ADMIN_REALM_UID)])
        ->set('allowed.0.realms', [$community->getShortCode()])
        ->call('save')
        ->assertHasNoErrors();

    $log = ActivityLog::sole();
    expect($log->action)->toBe('mailman_domain.updated')
        ->and($log->realm)->toBeNull()
        ->and($log->subject_label)->toBe('lists.example.org')
        ->and($log->details)->toBe(['realms' => ['old' => [], 'new' => [$community->getShortCode()]]]);
});

test('with a retention period a deleted realm keeps its log entries until they expire', function (): void {
    config(['activity_log.retention_days' => 365]);
    actingAsSuperAdmin();

    Livewire::test(NewRealm::class)
        ->set('uid', 'logtest')
        ->set('name', 'Protokolltest')
        ->call('save')
        ->assertHasNoErrors();
    Livewire::test(ListRealms::class)
        ->call('deletePrepare', 'logtest')
        ->set('deleteConfirmText', 'logtest')
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['realm.created', 'realm.deleted'])
        ->and($logs->pluck('realm')->unique()->all())->toBe(['logtest'])
        ->and($logs[1]->details['description'])->toBe('Protokolltest');
});

test('creating a realm is logged', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewRealm::class)
        ->set('uid', 'logtest')
        ->set('name', 'Protokolltest')
        ->call('save')
        ->assertHasNoErrors();

    $log = ActivityLog::sole();
    expect($log->action)->toBe('realm.created')
        ->and($log->realm)->toBe('logtest')
        ->and($log->subject_id)->toBe(Community::findByUid('logtest')->getConvertedGuid());
});

test('without a retention period nothing of a deleted realm stays in the log', function (): void {
    config(['activity_log.retention_days' => null]);
    actingAsSuperAdmin();

    Livewire::test(NewRealm::class)
        ->set('uid', 'logtest')
        ->set('name', 'Protokolltest')
        ->call('save');
    Livewire::test(ListRealms::class)
        ->call('deletePrepare', 'logtest')
        ->set('deleteConfirmText', 'logtest')
        ->call('deleteCommit');

    expect(ActivityLog::count())->toBe(0);
});
