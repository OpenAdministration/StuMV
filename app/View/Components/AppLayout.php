<?php

namespace App\View\Components;

use App\Ldap\Community;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /** @var array<string, mixed> */
    public array $routeParams;

    /**
     * Set by resources/views/errors/{404,403,500}.blade.php (the only
     * callers that pass it) so components/header.blade.php can append it to
     * the breadcrumb trail - the route being rendered for is whatever
     * legitimately matched (or the realm-scoped 404 catch-all), so its own
     * breadcrumb has no way to know an error is actually being shown.
     */
    public function __construct(public ?int $errorCode = null)
    {
        // Maybe there is a more Laravel way to make this work ...
        // Route::current() is null when this layout renders for a URL that
        // matched no route at all (e.g. a mistyped path) - most commonly
        // reached now from resources/views/errors/{404,403,500}.blade.php.
        $params = Route::current()?->parameters() ?? [];
        foreach ($params as $name => $entry) {
            // only {realm} is bound to an LDAP model (see RouteServiceProvider)
            if ($entry instanceof Community) {
                $params[$name] = $entry->getFirstAttribute($entry->getRouteKeyName());
            }
        }
        $this->routeParams = $params;
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app', [
            'title' => 'StuMV',
            'routeParams' => $this->routeParams,
            'errorCode' => $this->errorCode,
        ]);
    }
}
