<?php

use App\Ldap\Community;
use App\Livewire\Realm\ListActivityLogs;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

function logEntry(?string $realm, string $action, array $attributes = []): ActivityLog
{
    return ActivityLog::forceCreate(['realm' => $realm, 'action' => $action, ...$attributes]);
}

test('a realm admin sees their realm\'s entries with actor, action and changed fields', function (): void {
    $community = newCommunity();
    $admin = actingAsAdmin($community);
    logEntry($community->getShortCode(), 'role.updated', [
        'actor_type' => 'user',
        'actor_id' => $admin->uid,
        'subject_label' => 'Kassenwart',
        'details' => ['description' => ['old' => 'Kasse', 'new' => 'Kassenwart']],
    ]);
    logEntry(newCommunity()->getShortCode(), 'role.deleted', ['subject_label' => 'Fremde Rolle']);

    $this->get(route('realms.activity-log', ['realm' => $community->getShortCode()]))
        ->assertOk()
        ->assertSee(__('activity_log.actions.role.updated'))
        // a missing translation would show its key instead
        ->assertDontSee('activity_log.actions.')
        ->assertSee("{$admin->full_name} ({$admin->username})")
        ->assertSee(__('activity_log.fields.description').':')
        ->assertSee('Kasse → Kassenwart')
        ->assertDontSee('Fremde Rolle');
});

test('entries can be filtered by action, affected object and date', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);
    logEntry($uid, 'role.created', ['subject_label' => 'Kasse', 'created_at' => '2026-01-10 12:00:00']);
    logEntry($uid, 'group.created', ['subject_label' => 'Newsletter', 'created_at' => '2026-03-10 12:00:00']);

    Livewire::test(ListActivityLogs::class, ['realm' => $community])
        ->set('actions', ['group.created'])
        ->assertSee('Newsletter')->assertDontSee('Kasse')
        ->set('actions', ['group.created', 'role.created'])
        ->assertSee('Newsletter')->assertSee('Kasse')
        ->set('actions', [])
        ->set('search', 'kas')
        ->assertSee('Kasse')->assertDontSee('Newsletter')
        ->set('search', '')
        ->set('from', '2026-02-01')
        ->assertSee('Newsletter')->assertDontSee('Kasse');
});

test('the system and deleted accounts are shown as such', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    logEntry($community->getShortCode(), 'member.deleted', ['subject_label' => 'A']);
    logEntry($community->getShortCode(), 'role.created', ['subject_label' => 'B', 'actor_type' => 'user', 'actor_id' => 'gone-uuid']);

    Livewire::test(ListActivityLogs::class, ['realm' => $community])
        ->assertSee(__('activity_log.system'))
        ->assertSee(__('activity_log.deleted_actor'));
});

test('neither members nor moderators nor admins of another realm can open it', function (): void {
    $community = newCommunity();
    $route = route('realms.activity-log', ['realm' => $community->getShortCode()]);

    actingAsMember($community);
    $this->get($route)->assertForbidden();
    actingAsModerator($community);
    $this->get($route)->assertForbidden();
    actingAsAdmin(newCommunity());
    $this->get($route)->assertForbidden();
});

test('in the admin realm a superadmin sees the entries of every realm and those of none', function (): void {
    $community = newCommunity();
    logEntry($community->getShortCode(), 'role.created', ['subject_label' => 'Kasse']);
    logEntry(null, 'mailman_domain.updated', ['subject_label' => 'lists.example.org']);
    actingAsSuperAdmin();

    $this->get(route('realms.activity-log', ['realm' => Community::ADMIN_REALM_UID]))
        ->assertOk()
        ->assertSee('Kasse')
        ->assertSee($community->getShortCode())
        ->assertSee('lists.example.org');
});

test('the sidebar links to the activity log for admins only', function (): void {
    $community = newCommunity();
    $link = route('realms.activity-log', ['realm' => $community->getShortCode()]);

    actingAsModerator($community);
    $this->get(route('realms.dashboard', ['realm' => $community->getShortCode()]))->assertOk()->assertDontSee($link);
    actingAsAdmin($community);
    $this->get(route('realms.dashboard', ['realm' => $community->getShortCode()]))->assertOk()->assertSee($link);
});

test('a person referred to by entryUUID is shown by name while the account exists', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    actingAsAdmin($community);
    logEntry($community->getShortCode(), 'role_membership.created', ['subject_label' => 'mitglied (fsr)', 'details' => ['person' => $member->uid]]);
    logEntry($community->getShortCode(), 'role_membership.deleted', ['subject_label' => 'leitung (fsr)', 'details' => ['person' => 'gone-uuid']]);

    Livewire::test(ListActivityLogs::class, ['realm' => $community])
        ->assertSee(__('activity_log.fields.person').':')
        ->assertSee("{$member->full_name} ({$member->username})")
        ->assertSee('gone-uuid');
});
