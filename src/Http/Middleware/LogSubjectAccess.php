<?php

namespace JimBarrett\LaravelAuditLogger\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use JimBarrett\LaravelAuditLogger\Exceptions\AccessLogMisconfigured;
use JimBarrett\LaravelAuditLogger\Services\AccessLogService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs access to a route-bound subject.
 *
 * This is the coarse fallback. It fires once per successful page load, so an application
 * wanting finer detail (which tab, which panel) should call AccessLogService directly
 * from its own controllers.
 */
class LogSubjectAccess
{
    protected AccessLogService $accessLogService;

    public function __construct(AccessLogService $accessLogService)
    {
        $this->accessLogService = $accessLogService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $accessType Optional per-route override, e.g. 'log.access:chart_view'
     */
    public function handle(Request $request, Closure $next, ?string $accessType = null): Response
    {
        $response = $next($request);

        if (! config('audit-logger.access_log.enabled', true)) {
            return $response;
        }

        if ($response->getStatusCode() !== 200 || ! auth()->check()) {
            return $response;
        }

        $parameter = config('audit-logger.access_log.route_parameter');

        if (empty($parameter)) {
            throw AccessLogMisconfigured::missingRouteParameter();
        }

        $subject = $request->route($parameter);

        // A route that doesn't carry the subject at all is fine, the middleware simply
        // has nothing to record. A subject that came through unbound is not: it means
        // this runs before SubstituteBindings and would silently log nothing forever.
        if (is_null($subject)) {
            return $response;
        }

        if (! $subject instanceof Model) {
            throw AccessLogMisconfigured::unboundRouteParameter($parameter, get_debug_type($subject));
        }

        $this->accessLogService->log(
            subject: $subject,
            accessType: $accessType ?? config('audit-logger.access_log.default_access_type', 'view')
        );

        return $response;
    }
}
