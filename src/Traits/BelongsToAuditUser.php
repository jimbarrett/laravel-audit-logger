<?php

namespace JimBarrett\LaravelAuditLogger\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The user an audit or access log row is attributed to.
 */
trait BelongsToAuditUser
{
    /**
     * Get the user who made the change or accessed the record.
     *
     * Includes soft-deleted users when the configured user model soft-deletes. An audit
     * row has to name whoever acted even after they have been removed; otherwise their
     * changes read as anonymous, which is indistinguishable from a change nobody made.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        $userModel = config('audit-logger.user_model', \App\Models\User::class);
        $relation = $this->belongsTo($userModel);

        if (in_array(SoftDeletes::class, class_uses_recursive($userModel), true)) {
            $relation->withTrashed();
        }

        return $relation;
    }
}
