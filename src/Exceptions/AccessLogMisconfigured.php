<?php

namespace JimBarrett\LaravelAuditLogger\Exceptions;

use RuntimeException;

/**
 * Thrown when the access log middleware is wired up in a way that cannot work.
 */
class AccessLogMisconfigured extends RuntimeException
{
    /**
     * The route parameter resolved to something other than a model, which almost always
     * means the middleware runs before SubstituteBindings.
     *
     * @param string $parameter
     * @param string $actualType
     * @return static
     */
    public static function unboundRouteParameter(string $parameter, string $actualType): self
    {
        $message = sprintf(
            'Access log middleware received route parameter [%s] as %s rather than a model. '
            .'Register it after SubstituteBindings (for example inside the "web" middleware '
            .'group) so route model binding has already run.',
            $parameter,
            $actualType
        );

        return new self($message);
    }

    /**
     * No route parameter is configured, so the middleware has nothing to look for.
     *
     * @return static
     */
    public static function missingRouteParameter(): self
    {
        return new self(
            'Access log middleware is in use but audit-logger.access_log.route_parameter '
            .'is not set. Set it to the route binding holding the audited record, e.g. "patient".'
        );
    }
}
