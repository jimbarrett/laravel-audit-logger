<?php

namespace JimBarrett\LaravelAuditLogger\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use JimBarrett\LaravelAuditLogger\Exceptions\ContextNotConfigured;
use JimBarrett\LaravelAuditLogger\Support\AuditContext;

/**
 * Audit Log model for tracking model changes.
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'model_audits';

    public $timestamps = false;

    protected $fillable = ['model_type', 'model_id', 'action', 'user_id', 'context_id', 'sub_context_id', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Resolved rather than read straight from the auth guard and session, so that
            // seeders, jobs and console commands can attribute their writes instead of
            // leaving them anonymous and contextless. See AuditContext::scope().
            $context = app(AuditContext::class);
            $model->user_id = $model->user_id ?? $context->userId();
            $model->context_id = $model->context_id ?? $context->contextId();
            $model->sub_context_id = $model->sub_context_id ?? $context->subContextId();

            $model->created_at = now();
        });
    }

    /**
     * Get the user who made the change.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        $userModel = config('audit-logger.user_model', \App\Models\User::class);
        return $this->belongsTo($userModel);
    }

    /**
     * Get the context model (e.g., Practice, Community, Organization).
     *
     * @return BelongsTo
     */
    public function context(): BelongsTo
    {
        $contextModel = config('audit-logger.context_model');

        if (! $contextModel) {
            throw ContextNotConfigured::for('context_model', 'context');
        }

        return $this->belongsTo($contextModel, 'context_id');
    }

    /**
     * Get the sub-context model (e.g., Location within a Practice).
     *
     * @return BelongsTo
     */
    public function subContext(): BelongsTo
    {
        $subContextModel = config('audit-logger.sub_context_model');

        if (! $subContextModel) {
            throw ContextNotConfigured::for('sub_context_model', 'subContext');
        }

        return $this->belongsTo($subContextModel, 'sub_context_id');
    }

    /**
     * Get the record this audit describes.
     *
     * model_type holds a short class name (e.g. "Patient"), so the application must
     * register a morph map for this relation to resolve.
     *
     * @return MorphTo
     */
    public function model(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'model_type', 'model_id');
    }

    /**
     * Get the audit log entries for this audit log.
     *
     * @return HasMany
     */
    public function entries(): HasMany
    {
        return $this->hasMany(AuditLogEntry::class, 'model_audit_id');
    }
}
