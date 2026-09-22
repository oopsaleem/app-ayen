<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSetupAccessible
{
    /**
     * Handle an incoming request.
     *
     * While no Admin exists yet, the setup page is open to guests (first-run
     * bootstrap). Once one exists, only an authenticated Admin who has
     * recently re-confirmed their password may reach it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Admin::query()->exists()) {
            return $next($request);
        }

        return app(Authenticate::class)->handle($request, function (Request $request) use ($next) {
            abort_unless($request->user()->isAdmin(), 403);

            return app(RequirePassword::class)->handle($request, $next);
        });
    }
}
