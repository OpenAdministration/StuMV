<?php

use App\Support\MailmanClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function postoriusClient(string $password = 'owner-pass'): MailmanClient
{
    return new MailmanClient('http://api.test/3.1', 'restadmin', 'key', true, 'https://web.test', 'owner', $password);
}

/**
 * @param  array<int, string>  $roster
 */
function fakePostorius(array $roster, int $formStatus = 200): void
{
    $csrfInput = fn (string $token): string => '<input type="hidden" name="csrfmiddlewaretoken" value="'.$token.'">';

    Http::fake([
        'web.test/accounts/login/' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response($csrfInput('login-token').'<input name="password">')
            : Http::response($request['password'] === 'owner-pass' ? '<h1>Lists</h1>' : '<input name="password">'),
        'web.test/postorius/lists/*' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response($csrfInput('form-token').'<textarea name="emails"></textarea>', $formStatus)
            : Http::response('<p>done</p>'),
        'api.test/3.1/lists/*/roster/member' => Http::response([
            'entries' => array_map(fn (string $email): array => ['email' => $email, 'self_link' => 'http://api.test/3.1/members/1'], $roster),
        ]),
    ]);
}

test('subscribe logs in to Postorius and posts the mass subscribe form', function (): void {
    fakePostorius(['new@example.org']);

    postoriusClient()->subscribe('team.lists.example.org', 'new@example.org');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://web.test/accounts/login/'
        && $request['csrfmiddlewaretoken'] === 'login-token'
        && $request['login'] === 'owner');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://web.test/postorius/lists/team.lists.example.org/mass_subscribe/'
        && $request['csrfmiddlewaretoken'] === 'form-token'
        && $request['emails'] === 'new@example.org'
        && $request['pre_approved'] === 'on'
        && $request->header('Referer') === ['https://web.test/postorius/lists/team.lists.example.org/mass_subscribe/']);
    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'http://api.test/3.1/members'));
});

test('unsubscribe posts the mass removal form and logs in only once per client', function (): void {
    fakePostorius([]);
    $client = postoriusClient();

    $client->unsubscribe('team.lists.example.org', 'a@example.org', 'http://api.test/3.1/members/1');
    $client->unsubscribe('team.lists.example.org', 'b@example.org', 'http://api.test/3.1/members/2');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://web.test/postorius/lists/team.lists.example.org/mass_removal/'
        && $request['emails'] === 'b@example.org');
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://web.test/accounts/login/'))->toHaveCount(1);
});

test('subscribe fails when the roster does not show the address afterwards', function (): void {
    fakePostorius([]);

    postoriusClient()->subscribe('team.lists.example.org', 'new@example.org');
})->throws(RuntimeException::class, 'new@example.org is still not subscribed');

test('a list the Postorius user does not own fails with a clear message', function (): void {
    fakePostorius([], formStatus: 403);

    postoriusClient()->subscribe('team.lists.example.org', 'new@example.org');
})->throws(RuntimeException::class, 'owner is not an owner of this list');

test('wrong Postorius credentials fail the login', function (): void {
    fakePostorius([]);

    postoriusClient('wrong')->subscribe('team.lists.example.org', 'new@example.org');
})->throws(RuntimeException::class, 'Postorius login failed for owner');
