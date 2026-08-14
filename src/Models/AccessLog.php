<?php

namespace JimBarrett\LaravelAuditLogger\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use JimBarrett\LaravelAuditLogger\Exceptions\UnresolvableSubjectType;

/**
 * Access log model, recording reads of a subject rather than writes to it.
 *
 * The write-side counterpart is AuditLog. These are deliberately separate tables: a read
 * has no changed fields to record, fires from the HTTP layer rather than a model event,
 * and arrives in far higher volume.
 */
class AccessLog extends Model
{
    use HasFactory;

    protected $table = 'subject_access_logs';

    /**
     * Rows are immutable once written, so there is no updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'user_id',
        'access_type',
        'ip_address',
        'context_id',
        'sub_context_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the record that was accessed.
     *
     * subject_type holds a short class name (e.g. "Patient"), so the application must
     * register a morph map for this relation to resolve.
     *
     * @return MorphTo
     */
    public function subject(): MorphTo
    {
        if (! is_null($this->subject_type) && is_null(Relation::getMorphedModel($this->subject_type))
            && ! class_exists($this->subject_type)) {
            throw UnresolvableSubjectType::for($this->subject_type);
        }

        return $this->morphTo();
    }

    /**
     * Get the user who accessed the record.
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
     * @return BelongsTo|null
     */
    public function context(): ?BelongsTo
    {
        $contextModel = config('audit-logger.context_model');

        if (! $contextModel) {
            return null;
        }

        return $this->belongsTo($contextModel, 'context_id');
    }

    /**
     * Get the sub-context model (e.g., Location within a Practice).
     *
     * @return BelongsTo|null
     */
    public function subContext(): ?BelongsTo
    {
        $subContextModel = config('audit-logger.sub_context_model');

        if (! $subContextModel) {
            return null;
        }

        return $this->belongsTo($subContextModel, 'sub_context_id');
    }

    /**
     * Scope to filter by access type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('access_type', $type);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter to a single subject record.
     */
    public function scopeForSubject($query, Model $subject)
    {
        return $query
            ->where('subject_type', (new \ReflectionClass($subject))->getShortName())
            ->where('subject_id', $subject->getKey());
    }
}
