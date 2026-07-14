<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;

/**
 * league/oauth2-server throws a bare LogicException ("Invalid key supplied")
 * when Passport's configured encryption key file doesn't exist (e.g.
 * `php artisan passport:keys` was never run in that environment) - it isn't
 * caught anywhere upstream, so without the mapping in App\Exceptions\Handler
 * it leaked straight to the client as an uncaught 500.
 *
 * These call $kernel->handle() directly instead of $this->getJson(), because
 * Kernel::terminate() (invoked by MakesHttpRequests::call() right after
 * handle()) re-resolves every route middleware a second time just to check
 * for a terminate() method - which re-triggers the exact same LogicException
 * a second time, uncaught, since the underlying broken key path is still in
 * effect. That second throw is a real quirk (it happens in production too),
 * but it only affects server-side termination *after* the response has
 * already been sent to the client, so it isn't what these tests cover.
 */
test('a broken Passport encryption key renders as 403, not a leaked LogicException', function (): void {
    $originalKeyPath = Passport::$keyPath;
    Passport::loadKeysFrom('/this/path/does/not/exist');

    try {
        $kernel = $this->app->make(Kernel::class);
        $response = $kernel->handle(Request::create(
            '/api/demo/committees', 'GET', server: ['HTTP_ACCEPT' => 'application/json']
        ));

        expect($response->getStatusCode())->toBe(403);
        expect($response->getContent())->not->toContain('Invalid key supplied');
    } finally {
        Passport::$keyPath = $originalKeyPath;
    }
});

test('an unrelated LogicException is not swallowed by the Passport key mapping', function (): void {
    $handler = resolve(ExceptionHandler::class);

    $exception = new LogicException('Some unrelated programming error');

    $response = $handler->render(request(), $exception);

    expect($response->getStatusCode())->toBe(500);
});
