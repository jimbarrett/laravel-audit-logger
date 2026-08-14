<?php

namespace JimBarrett\LaravelAuditLogger\Support;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves who performed an audited action and which tenant it belongs to.
 *
 * Both are derived from the request by default: the user from the auth guard, the context
 * from the session. Seeders, queue jobs, console commands and migrations have neither, so
 * they must set them explicitly or their rows land unattributed.
 *
 * User and context are tracked separately because they vary independently. A queued job
 * dispatched from a request knows its user but has lost the session; a nightly command
 * knows the tenant it is processing but has no user at all.
 */
class AuditContext
{
    /**
     * Explicitly set context ID, taking precedence over the session.
     *
     * @var int|null
     */
    protected $contextId = null;

    /**
     * Explicitly set sub-context ID, taking precedence over the session.
     *
     * @var int|null
     */
    protected $subContextId = null;

    /**
     * Explicitly set user ID, taking precedence over the auth guard.
     *
     * @var int|null
     */
    protected $userId = null;

    /**
     * Whether an explicit context has been set.
     *
     * @var bool
     */
    protected $contextOverridden = false;

    /**
     * Whether an explicit user has been set.
     *
     * @var bool
     */
    protected $userOverridden = false;

    /**
     * Set the context explicitly, overriding whatever the session holds.
     *
     * @param int|null $contextId
     * @param int|null $subContextId
     * @return void
     */
    public function override(?int $contextId, ?int $subContextId = null): void
    {
        $this->contextId = $contextId;
        $this->subContextId = $subContextId;
        $this->contextOverridden = true;
    }

    /**
     * Attribute subsequent writes to a user, overriding the auth guard.
     *
     * Pass null to record explicitly unattributed writes, which is the honest option for
     * genuinely automated work rather than borrowing whichever user happens to be handy.
     *
     * @param int|null $userId
     * @return void
     */
    public function actingAs(?int $userId): void
    {
        $this->userId = $userId;
        $this->userOverridden = true;
    }

    /**
     * Drop every explicit value and fall back to the request again.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->contextId = null;
        $this->subContextId = null;
        $this->userId = null;
        $this->contextOverridden = false;
        $this->userOverridden = false;
    }

    /**
     * Run a callback with explicit attribution, restoring the previous state afterwards.
     *
     * Use this in seeders, jobs and console commands so their writes are attributable:
     *
     *     app(AuditContext::class)->scope($practice->id, $location->id, function () {
     *         Patient::factory()->count(20)->create();
     *     }, userId: $admin->id);
     *
     * @param int|null $contextId
     * @param int|null $subContextId
     * @param \Closure $callback
     * @param int|null $userId Omit to leave user attribution as it already is
     * @return mixed
     */
    public function scope(?int $contextId, ?int $subContextId, Closure $callback, ?int $userId = null)
    {
        $previous = [
            $this->contextId,
            $this->subContextId,
            $this->userId,
            $this->contextOverridden,
            $this->userOverridden,
        ];

        $this->override($contextId, $subContextId);

        if (! is_null($userId)) {
            $this->actingAs($userId);
        }

        try {
            return $callback();
        } finally {
            [
                $this->contextId,
                $this->subContextId,
                $this->userId,
                $this->contextOverridden,
                $this->userOverridden,
            ] = $previous;
        }
    }

    /**
     * Whether an explicit context is currently in force.
     *
     * @return bool
     */
    public function isOverridden(): bool
    {
        return $this->contextOverridden;
    }

    /**
     * Whether an explicit user is currently in force.
     *
     * @return bool
     */
    public function hasUserOverride(): bool
    {
        return $this->userOverridden;
    }

    /**
     * Resolve the context ID: explicit value first, session second.
     *
     * @return int|null
     */
    public function contextId(): ?int
    {
        if ($this->contextOverridden) {
            return $this->contextId;
        }

        return $this->fromSession(config('audit-logger.context_session_key'));
    }

    /**
     * Resolve the sub-context ID: explicit value first, session second.
     *
     * @return int|null
     */
    public function subContextId(): ?int
    {
        if ($this->contextOverridden) {
            return $this->subContextId;
        }

        return $this->fromSession(config('audit-logger.sub_context_session_key'));
    }

    /**
     * Resolve the acting user ID: explicit value first, auth guard second.
     *
     * @return int|null
     */
    public function userId(): ?int
    {
        if ($this->userOverridden) {
            return $this->userId;
        }

        return Auth::id();
    }

    /**
     * Read a key from the session, if there is a started session to read from.
     *
     * Console and queue processes have no started session. Checking rather than reading
     * blindly keeps this from depending on the session driver's null-handling.
     *
     * @param string|null $key
     * @return int|null
     */
    protected function fromSession(?string $key): ?int
    {
        if (empty($key)) {
            return null;
        }

        if (! app()->bound('session.store') || ! app('session.store')->isStarted()) {
            return null;
        }

        $value = app('session.store')->get($key);

        return is_null($value) ? null : (int) $value;
    }
}
