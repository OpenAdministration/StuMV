<?php

use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\Support\TestLdap;

/**
 * Livewire actions are separate requests to its own endpoint. Only middleware
 * registered as persistent runs again there, so these go through real HTTP
 * instead of Livewire::test(), which skips route middleware altogether.
 */
function callLivewireAction(string $html, string $method): TestResponse
{
    preg_match('/wire:snapshot="([^"]+)"/', $html, $match);
    $snapshot = html_entity_decode($match[1] ?? '', ENT_QUOTES);

    return test()->withHeaders(['X-Livewire' => 'true'])->postJson(resolve(HandleRequests::class)->getUpdateUri(), [
        'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => $method, 'params' => []]]]],
    ]);
}

test('a moderator who lost the role in the meantime can no longer save', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'kasse');
    $moderator = actingAsModerator($community);

    $page = $this->get(route('committees.roles.edit', ['realm' => $community->getShortCode(), 'ou' => 'fsr', 'cn' => 'kasse']))
        ->assertOk();

    $community->moderatorsGroup()->members()->detach($moderator->ldap());

    callLivewireAction($page->getContent(), 'save')->assertForbidden();
});

test('a moderator can still save through the livewire endpoint', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'kasse');
    actingAsModerator($community);

    $page = $this->get(route('committees.roles.edit', ['realm' => $community->getShortCode(), 'ou' => 'fsr', 'cn' => 'kasse']))
        ->assertOk();

    callLivewireAction($page->getContent(), 'save')->assertOk();
});

test('livewire actions still work behind each authorization middleware', function (string $as, string $route, array $params): void {
    $community = newCommunity();
    TestLdap::makeRole(TestLdap::makeCommittee($community, 'fsr'), 'kasse');
    match ($as) {
        'member' => actingAsMember($community),
        'moderator' => actingAsModerator($community),
        'admin' => actingAsAdmin($community),
        'superadmin' => actingAsSuperAdmin(),
    };

    $page = $this->get(route($route, ['realm' => $community->getShortCode(), ...$params]))->assertOk();

    callLivewireAction($page->getContent(), '$refresh')->assertOk();
})->with([
    'communityMember' => ['member', 'realms.members', []],
    'communityMod' => ['moderator', 'committees.roles.members', ['ou' => 'fsr', 'cn' => 'kasse']],
    'can:moderator' => ['moderator', 'committees.new', []],
    'can:tools' => ['moderator', 'tools.invite-user', []],
    'communityAdmin' => ['admin', 'realms.groups', []],
    'superadmin' => ['superadmin', 'realms.members.new', []],
]);
