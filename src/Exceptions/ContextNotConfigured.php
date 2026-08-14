<?php

namespace JimBarrett\LaravelAuditLogger\Exceptions;

use LogicException;

/**
 * Thrown when a context relation is used without a model configured for it.
 */
class ContextNotConfigured extends LogicException
{
    /**
     * @param string $configKey The audit-logger config key that is unset
     * @param string $relation The relation method that was called
     * @return static
     */
    public static function for(string $configKey, string $relation): self
    {
        return new self(sprintf(
            'Cannot load the [%s] relation because audit-logger.%s is not configured. '
            .'Set it to the model backing that tenancy level, or stop using the relation.',
            $relation,
            $configKey
        ));
    }
}
