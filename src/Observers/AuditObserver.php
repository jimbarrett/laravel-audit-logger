<?php

namespace JimBarrett\LaravelAuditLogger\Observers;

use JimBarrett\LaravelAuditLogger\Models\AuditLog;
use JimBarrett\LaravelAuditLogger\Models\AuditLogEntry;
use JimBarrett\LaravelAuditLogger\Services\AuditLogService;
use JimBarrett\LaravelAuditLogger\Traits\ResolveModelId;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Observer for auditing model changes.
 */
class AuditObserver
{
    use ResolveModelId;

    /**
     * Fields that should be redacted in logs.
     *
     * @var array
     */
    protected $sensitiveFields = [];

    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
        $this->sensitiveFields = config('audit-logger.sensitive_fields', [
            'password',
            'password_hash',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ]);
    }

    /**
     * Handle the model "created" event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function created($model)
    {
        $this->logAction($model, 'created');
    }

    /**
     * Handle the model "updated" event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function updated($model)
    {
        // Skip logging if this is a restore operation
        // Check if deleted_at changed from a date to null
        $original = $model->getOriginal('deleted_at');
        $current = $model->deleted_at;
        
        if (!is_null($original) && is_null($current)) {
            // This is a restore, it will be logged by the restored event
            return;
        }

        $this->logAction($model, 'updated');
    }

    /**
     * Handle the model "deleted" event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function deleted($model)
    {
        $isSoftDelete = method_exists($model, 'isForceDeleting') && !$model->isForceDeleting();
        
        // For soft deletes, just log status change. For hard deletes, capture all data.
        $this->logAction($model, 'deleted', !$isSoftDelete);
    }

    /**
     * Handle the model "restored" event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function restored($model)
    {
        $this->logAction($model, 'restored');
    }

    /**
     * Log an action for the model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $action
     * @param bool $captureAll Whether to capture all attributes (for delete)
     * @return void
     */
    protected function logAction($model, string $action, bool $captureAll = false)
    {
        $class = (new \ReflectionClass($model))->getShortName();
        $idField = $this->resolveIdField($model);
        $modelId = $model->$idField;

        // For updates, only log if there are actual changes
        if ($action === 'updated') {
            $loggable = $this->auditLogService->getLoggableChanges($model);
            
            if (empty($loggable)) {
                Log::debug("No changes to write to AuditLog: class={$class}, model_id={$modelId}, action={$action}");
                return;
            }
        }

        try {
            $auditLog = AuditLog::create([
                'model_type' => $class,
                'model_id' => $modelId,
                'action' => $action,
            ]);

            // Prepare log entries based on action type
            $logEntries = [];

            if ($action === 'created') {
                // Log all non-sensitive attributes for created records
                $attributes = $model->getAttributes();
                foreach ($attributes as $key => $value) {
                    if (in_array($key, ['created_at', 'updated_at', 'deleted_at'])) {
                        continue;
                    }
                    $logEntries[] = $this->redactLogEntry([
                        'model_audit_id' => $auditLog->id,
                        'updated_field' => $key,
                        'old_value' => null,
                        'new_value' => $value,
                    ]);
                }
            } elseif ($action === 'updated') {
                // Log only changed fields
                $loggable = $this->auditLogService->getLoggableChanges($model);
                foreach ($loggable as $key => $value) {
                    $logEntries[] = $this->redactLogEntry([
                        'model_audit_id' => $auditLog->id,
                        'updated_field' => $key,
                        'old_value' => $model->getOriginal($key),
                        'new_value' => $value,
                    ]);
                }
            } elseif ($action === 'deleted' || $captureAll) {
                if ($captureAll) {
                    // Hard delete: Log all attributes before deletion (so we have a record)
                    $attributes = $model->getAttributes();
                    foreach ($attributes as $key => $value) {
                        if (in_array($key, ['created_at', 'updated_at', 'deleted_at'])) {
                            continue;
                        }
                        $logEntries[] = $this->redactLogEntry([
                            'model_audit_id' => $auditLog->id,
                            'updated_field' => $key,
                            'old_value' => $value,
                            'new_value' => null,
                        ]);
                    }
                } else {
                    // Soft delete: Just log status change
                    $logEntries[] = [
                        'model_audit_id' => $auditLog->id,
                        'updated_field' => 'status',
                        'old_value' => 'active',
                        'new_value' => 'soft_deleted',
                    ];
                }
            } elseif ($action === 'restored') {
                // Log restoration event - show that record was restored from soft delete
                $logEntries[] = [
                    'model_audit_id' => $auditLog->id,
                    'updated_field' => 'status',
                    'old_value' => 'soft_deleted',
                    'new_value' => 'restored',
                ];
            }

            if (!empty($logEntries)) {
                AuditLogEntry::insert($logEntries);
            }
        } catch (Exception $e) {
            Log::error("Error inserting AuditLog: class={$class}, model_id={$modelId}, action={$action} ".$e->getMessage());
        }
    }

    /**
     * Redact sensitive fields in log entry.
     *
     * @param array $logEntry
     * @return array
     */
    protected function redactLogEntry(array $logEntry): array
    {
        if (in_array($logEntry['updated_field'], $this->sensitiveFields)) {
            $logEntry['old_value'] = '***REDACTED***';
            $logEntry['new_value'] = '***REDACTED***';
        }

        return $logEntry;
    }
}
