<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use LdapRecord\Query\ObjectNotFoundException;
use Psr\Log\LogLevel;
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
        $this->renderable(function (ObjectNotFoundException $e, $request) {
            return $this->render($request, new NotFoundHttpException($e->getMessage(), $e));
        });

        $this->reportable(function (Throwable $e): void {
            //
        });
    }
}
