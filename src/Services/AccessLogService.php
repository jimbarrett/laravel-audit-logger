<?php

namespace JimBarrett\LaravelAuditLogger\Services;

use Illuminate\Database\Eloquent\Model;
use JimBarrett\LaravelAuditLogger\Models\AccessLog;
use JimBarrett\LaravelAuditLogger\Support\AuditContext;
use JimBarrett\LaravelAuditLogger\Traits\ResolveModelId;

/**
 * Service for recording reads of audited records.
 */
class AccessLogService
{
    use ResolveModelId;

    protected AuditContext $auditContext;

    public function __construct(AuditContext $auditContext)
    {
        $this->auditContext = $auditContext;
    }

    /**
     * Record that a subject was accessed.
     *
     * Repeat access to the same subject, by the same user, of the same type, inside the
     * dedupe window is not recorded. Reads arrive in far higher volume than writes, and
     * one row per tab click would swamp the table.
     *
     * @param \Illuminate\Database\Eloquent\Model $subject The record that was accessed
     * @param string $accessType Application-defined label (e.g. 'profile_view', 'notes')
     * @param int|null $userId Defaults to the authenticated user
     * @param string|null $ipAddress Defaults to the current request IP
     * @param int|null $contextId Defaults to the resolved audit context
     * @param int|null $subContextId Defaults to the resolved audit sub-context
     * @return bool Whether a row was written
     */
    public function log(
        Model $subject,
        string $accessType,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?int $contextId = null,
        ?int $subContextId = null
    ): bool {
        $subjectType = (new \ReflectionClass($subject))->getShortName();
        $subjectId = $subject->{$this->resolveIdField($subject)};
        $userId = $userId ?? $this->auditContext->userId();

        if (is_null($userId) || is_null($subjectId)) {
            return false;
        }

        if ($this->recentlyLogged($subjectType, $subjectId, $userId, $accessType)) {
            return false;
        }

        AccessLog::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'user_id' => $userId,
            'access_type' => $accessType,
            'ip_address' => $ipAddress ?? (request() ? request()->ip() : null),
            'context_id' => $contextId ?? $this->auditContext->contextId(),
            'sub_context_id' => $subContextId ?? $this->auditContext->subContextId(),
        ]);

        return true;
    }

    /**
     * Whether an identical access was already recorded inside the dedupe window.
     *
     * @param string $subjectType
     * @param mixed $subjectId
     * @param int $userId
     * @param string $accessType
     * @return bool
     */
    protected function recentlyLogged(string $subjectType, $subjectId, int $userId, string $accessType): bool
    {
        $minutes = (int) config('audit-logger.access_log.dedupe_minutes', 5);

        if ($minutes <= 0) {
            return false;
        }

        return AccessLog::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('user_id', $userId)
            ->where('access_type', $accessType)
            ->where('created_at', '>', now()->subMinutes($minutes))
            ->exists();
    }
}
