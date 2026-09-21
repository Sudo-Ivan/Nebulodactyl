<?php

namespace Pterodactyl\Http\Middleware\Admin;

use Illuminate\Http\Request;
use Pterodactyl\Facades\Activity;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every mutating admin request to the activity log under an
 * admin: prefixed event so there is a durable audit trail of panel-side
 * changes (nodes, users, eggs, settings, transfers, and so on).
 *
 * Only request metadata is stored: the route name, route parameters, the
 * names of submitted fields, and the response status. Field values are
 * never logged since admin forms routinely carry secrets.
 */
class AuditAdminActions
{
    private const AUDITED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, \Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Log after the response so a slow audit write never delays the admin,
     * and so the recorded status reflects the final outcome.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (!in_array($request->method(), self::AUDITED_METHODS, true)) {
            return;
        }

        $user = $request->user();
        if (is_null($user) || !$user->root_admin) {
            return;
        }

        $route = $request->route();
        $name = $route?->getName() ?? 'unknown';

        $parameters = collect($route?->parameters() ?? [])
            ->map(fn ($value, $key) => $value instanceof Model ? $value->getKey() : $value)
            ->all();

        Activity::event(sprintf('admin:%s', $name))
            ->property([
                'method' => $request->method(),
                'uri' => $request->path(),
                'route_parameters' => $parameters,
                'fields' => array_values(array_diff(array_keys($request->all()), ['_token', '_method'])),
                'status' => $response->getStatusCode(),
            ])
            ->withRequestMetadata()
            ->log();
    }
}
