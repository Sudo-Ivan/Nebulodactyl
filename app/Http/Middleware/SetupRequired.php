<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Pterodactyl\Services\Setup\SetupLinkService;

/**
 * Gates the first-run setup routes.
 *
 * The setup flow is the only unauthenticated endpoint capable of creating an
 * account with administrator privileges. Two conditions must hold for a setup
 * route to answer at all:
 *
 *   1. The users table is empty. The moment any account exists - administrator
 *      or not - every setup route hard-fails with a 404, so the surface simply
 *      disappears once installation is complete.
 *   2. The request carries a valid setup key. Keys are minted at install time
 *      and by php artisan p:setup:link, and expire after one hour. An invalid
 *      or expired key gets a short explanation instead of the form.
 */
class SetupRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->exists()) {
            abort(404);
        }

        if (!app(SetupLinkService::class)->isValid($request->query('key'))) {
            if ($request->expectsJson()) {
                abort(403, 'This setup link is invalid or has expired.');
            }

            return response()->view('errors.setup-link', [], 403);
        }

        return $next($request);
    }
}
