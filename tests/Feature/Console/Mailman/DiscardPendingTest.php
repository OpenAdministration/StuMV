<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.mailman.api_url' => 'http://mailman.test/3.1']);
});

function fakePendingRequests(): void
{
    Http::fake([
        'mailman.test/3.1/lists/team.lists.example.org/requests/*' => Http::response('', 204),
        'mailman.test/3.1/lists/team.lists.example.org/requests' => Http::response(['entries' => [
            ['type' => 'subscription', 'email' => 'unconfirmed@example.org', 'token' => 'tok-sub', 'token_owner' => 'subscriber'],
            ['type' => 'subscription', 'email' => 'awaiting@example.org', 'token' => 'tok-mod', 'token_owner' => 'moderator'],
        ]]),
    ]);
}

test('only requests waiting for the subscriber are discarded by default', function (): void {
    fakePendingRequests();

    $this->artisan('mailman:discard-pending', ['list_id' => 'team.lists.example.org'])->assertExitCode(0);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'http://mailman.test/3.1/lists/team.lists.example.org/requests/tok-sub'
        && $request['action'] === 'discard');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/requests/tok-mod'));
});

test('--moderator also discards requests waiting for a moderator', function (): void {
    fakePendingRequests();

    $this->artisan('mailman:discard-pending', ['list_id' => 'team.lists.example.org', '--moderator' => true])->assertExitCode(0);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/requests/tok-sub'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/requests/tok-mod'));
});

test('--dry-run only lists what would be discarded', function (): void {
    fakePendingRequests();

    $this->artisan('mailman:discard-pending', ['list_id' => 'team.lists.example.org', '--dry-run' => true])
        ->expectsOutputToContain('unconfirmed@example.org')
        ->assertExitCode(0);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

test('a failing discard is reported and fails the command', function (): void {
    // Registered first so it wins over the catch-all in fakePendingRequests().
    Http::fake(['mailman.test/3.1/lists/team.lists.example.org/requests/tok-sub' => Http::response('', 500)]);
    fakePendingRequests();

    $this->artisan('mailman:discard-pending', ['list_id' => 'team.lists.example.org'])->assertExitCode(1);
});
