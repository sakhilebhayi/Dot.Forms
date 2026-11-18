<?php

namespace App\Http\Middleware;

use App\Support\CentralSignIn;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With the R-54 central sign-in on (DOTSSO_ENABLED, unless the break-glass DOTSSO_ALLOW_LOCAL_LOGIN is set), a password,
 * reset or two-factor post here must not become a second front door. The shared package already turns the login, forgot
 * and reset posts away; this also closes the two-factor challenge post and the Fortify handlers by name. Forms has no older
 * ECOSYSTEM_SSO_ONLY lock, so with the flag off nothing here changes.
 */
class RefuseLocalSignIn
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(CentralSignIn::active() && ! $request->isMethodSafe() && $request->routeIs('login.store', 'password.email', 'password.update', 'two-factor.login.store'), 404);

        return $next($request);
    }
}
