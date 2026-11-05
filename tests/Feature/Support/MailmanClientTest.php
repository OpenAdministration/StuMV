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

test('subscribe sends all addresses in one Postorius mass subscribe form', function (): void {
    fakePostorius(['a@example.org', 'b@example.org']);

    $failed = postoriusClient()->subscribe('team.lists.example.org', ['a@example.org', 'b@example.org']);

    expect($failed)->toBe([]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://web.test/accounts/login/'
        && $request['csrfmiddlewaretoken'] === 'login-token'
        && $request['login'] === 'owner');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://web.test/postorius/lists/team.lists.example.org/mass_subscribe/'
        && $request['csrfmiddlewaretoken'] === 'form-token'
        && $request['emails'] === "a@example.org\nb@example.org"
        && $request['pre_approved'] === 'on'
        && $request->header('Referer') === ['https://web.test/postorius/lists/team.lists.example.org/mass_subscribe/']);
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/mass_subscribe/')))->toHaveCount(1);
    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'http://api.test/3.1/members'));
});

test('unsubscribe posts the mass removal form and logs in only once per client', function (): void {
    fakePostorius([]);
    $client = postoriusClient();

    $client->unsubscribe('team.lists.example.org', [['email' => 'a@example.org', 'self_link' => 'http://api.test/3.1/members/1']]);
    $client->unsubscribe('team.lists.example.org', [['email' => 'b@example.org', 'self_link' => 'http://api.test/3.1/members/2']]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://web.test/postorius/lists/team.lists.example.org/mass_removal/'
        && $request['emails'] === 'b@example.org');
    expect(Http::recorded(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://web.test/accounts/login/'))->toHaveCount(1);
});

test('addresses missing from the roster afterwards are reported as failed', function (): void {
    fakePostorius(['a@example.org']);

    $failed = postoriusClient()->subscribe('team.lists.example.org', ['a@example.org', 'b@example.org']);

    expect($failed)->toBe(['b@example.org' => 'still not subscribed after the Postorius request']);
});

test('a list the Postorius user does not own fails every address with a clear message', function (): void {
    fakePostorius([], formStatus: 403);

    $failed = postoriusClient()->subscribe('team.lists.example.org', ['a@example.org', 'b@example.org']);

    expect($failed)->toBe([
        'a@example.org' => 'owner is not an owner of this list in Postorius',
        'b@example.org' => 'owner is not an owner of this list in Postorius',
    ]);
});

test('wrong Postorius credentials fail the login', function (): void {
    fakePostorius([]);

    $failed = postoriusClient('wrong')->subscribe('team.lists.example.org', ['new@example.org']);

    expect($failed)->toBe(['new@example.org' => 'Postorius login failed for owner']);
});

test('without Postorius each address is subscribed via REST and failures are reported per address', function (): void {
    Http::fake([
        'api.test/3.1/members' => fn (Request $request) => Http::response('', $request['subscriber'] === 'bad@example.org' ? 400 : 201),
    ]);
    $client = new MailmanClient('http://api.test/3.1', 'restadmin', 'key');

    $failed = $client->subscribe('team.lists.example.org', ['good@example.org', 'bad@example.org']);

    expect(array_keys($failed))->toBe(['bad@example.org']);
    Http::assertSentCount(2);
});

test('large Postorius batches are sent in chunks of 25', function (): void {
    $emails = array_map(fn (int $i): string => "user{$i}@example.org", range(1, 30));
    fakePostorius($emails);

    $failed = postoriusClient()->subscribe('team.lists.example.org', $emails);

    expect($failed)->toBe([]);
    $posts = Http::recorded(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/mass_subscribe/'))->values();
    expect($posts)->toHaveCount(2)
        ->and(substr_count($posts[0][0]['emails'], "\n") + 1)->toBe(25)
        ->and(substr_count($posts[1][0]['emails'], "\n") + 1)->toBe(5);
});
