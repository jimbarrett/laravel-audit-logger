<?php

namespace JimBarrett\LaravelAuditLogger\Exceptions;

use RuntimeException;

/**
 * Thrown when an access log's subject_type cannot be resolved back to a model class.
 */
class UnresolvableSubjectType extends RuntimeException
{
    /**
     * @param string $type The unresolvable subject_type value
     * @return static
     */
    public static function for(string $type): self
    {
        $message = sprintf(
            'Audit logger cannot resolve subject_type [%s] to a model class. Short class '
            .'names are stored on purpose, so the application must register a morph map '
            .'in a service provider, for example: '
            ."Relation::enforceMorphMap(['%s' => \\App\\Models\\%s::class]);",
            $type,
            $type,
            $type
        );

        return new self($message);
    }
}
