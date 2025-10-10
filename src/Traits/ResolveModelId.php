<?php

namespace JimBarrett\LaravelAuditLogger\Traits;

/**
 * Trait for resolving the ID field of a model.
 */
trait ResolveModelId
{
    /**
     * Resolve the ID field name for a given model.
     *
     * @param object $model
     * @return string
     */
    public function resolveIdField($model): string
    {
        if (! is_object($model)) {
            throw new \InvalidArgumentException('ResolveModelId::resolveIdField() expects an object, got '.gettype($model));
        }

        // If this is an Eloquent model, use its declared primary key
        if (method_exists($model, 'getKeyName')) {
            $key = $model->getKeyName();
            if (! empty($key)) {
                return $key;
            }
        }

        // Map the ID field for any model that doesn't use 'id'
        // Can be extended via config
        $idFields = config('audit-logger.custom_id_fields', []);

        return $idFields[get_class($model)] ?? 'id';
    }
}
