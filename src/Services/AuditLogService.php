<?php

namespace JimBarrett\LaravelAuditLogger\Services;

/**
 * Service for handling audit log logic.
 */
class AuditLogService
{
    /**
     * Get the loggable changes from a model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    public function getLoggableChanges($model): array
    {
        $original = $model->getOriginal();
        $changes = $model->getChanges();
        $loggableChanges = [];

        foreach ($changes as $key => $value) {
            // Skip updated_at and created_at timestamps
            if (in_array($key, ['updated_at', 'created_at'])) {
                continue;
            }

            // Check if the value has actually changed
            if (! $this->checkDeepMatch($original[$key] ?? null, $value)) {
                $loggableChanges[$key] = $value;
            }
        }

        return $loggableChanges;
    }

    /**
     * Deep comparison of old and new values.
     *
     * @param mixed $old
     * @param mixed $new
     * @return bool
     */
    public function checkDeepMatch($old, $new): bool
    {
        // Exact match
        if ($old == $new) {
            return true;
        }

        // Both null
        if (is_null($old) && is_null($new)) {
            return true;
        }

        // Handle integer/string comparison in both directions
        // e.g., "1" vs 1 OR 1 vs "1"
        if (is_int($new) && intval($old) == $new) {
            return true;
        }
        
        if (is_int($old) && intval($new) == $old) {
            return true;
        }

        return false;
    }
}
