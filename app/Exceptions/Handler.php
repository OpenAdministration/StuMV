<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use LdapRecord\Query\ObjectNotFoundException;
use LogicException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    #[\Override]
    public function register()
    {
        // LdapRecord's findOrFail()-style lookups (e.g. the global "uid"
        // route binding resolving a Community) throw this when nothing
        // matches - render it like Eloquent's ModelNotFoundException
        // (a 404) instead of leaking as an uncaught 500.
        $this->renderable(fn (ObjectNotFoundException $e, $request) => $this->render($request, new NotFoundHttpException($e->getMessage(), $e)));

        // league/oauth2-server throws a bare LogicException (not caught by
        // Passport's own ValidateToken::validateToken()) when it can't load
        // its configured encryption key file - e.g. `php artisan
        // passport:keys` was never run in this environment. That's a server
        // misconfiguration, not something a caller can fix, but it must
        // never leak "Invalid key supplied"/file paths to an API client.
        // Only intercept LogicExceptions that actually came from that
        // package - LogicException is a common base SPL exception used for
        // unrelated bugs elsewhere, which must keep rendering normally.
        $this->renderable(function (LogicException $e, $request) {
            if (! str_contains($e->getFile(), 'league'.DIRECTORY_SEPARATOR.'oauth2-server')) {
                return null;
            }

            return $this->render($request, new AccessDeniedHttpException(
                'Unable to authenticate the request due to a server configuration issue.',
                $e
            ));
        });

        $this->reportable(function (Throwable $e): void {
            //
        });
    }
}
