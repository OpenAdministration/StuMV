<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * routes/web.php registers Passport's own routes/web.php (see the
     * "realm.passport." group) *inside* this file, which the
     * RouteServiceProvider already wraps in the "web" middleware group -
     * unlike a normal Passport install, where routes/web.php's own
     * 'middleware' => 'throttle' (deliberately NOT 'web') on /token,
     * /token/refresh and /device/code is what's actually registered, these
     * three end up CSRF-checked anyway just by virtue of living in this
     * file. All three are called server-to-server by an OIDC/OAuth client,
     * never from a form this app rendered - there's no session or CSRF
     * token to check in the first place, so every such call got rejected
     * with 419 right after an otherwise-successful login+consent.
     * oauth/end-session is the same story from the other direction: a
     * client may redirect the user's browser here via a form POST (OpenID
     * Connect RP-Initiated Logout 1.0 explicitly allows either GET or POST),
     * but that form lives on the *client's* page, not one this app rendered
     * with a matching _token.
     *
     * identity-provider/*\/backchannel-logout is StuMV acting as an OIDC
     * *client* to an external identity provider: that provider posts a
     * logout_token here directly, server-to-server, when its own user logs
     * out - there's no browser/session/form involved on this end at all.
     *
     * oauth/introspect and oauth/revoke are the same server-to-server story
     * as oauth/token: a resource server (or the client itself) calls them
     * directly with client credentials, never via a form this app rendered.
     *
     * @var array<int, string>
     */
    protected $except = [
        '*/oauth/token',
        '*/oauth/token/refresh',
        '*/oauth/device/code',
        '*/oauth/end-session',
        '*/oauth/introspect',
        '*/oauth/revoke',
        '*/identity-provider/*/backchannel-logout',
    ];
}
