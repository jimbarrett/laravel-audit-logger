<?php

namespace JimBarrett\LaravelAuditLogger\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * Audit Log model for tracking model changes.
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'model_audits';

    public $timestamps = false;

    protected $fillable = ['model_type', 'model_id', 'action', 'user_id', 'context_id', 'created_at'];

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
            $model->user_id = Auth::id() ?? null;
            
            // Get context ID from configured session key
            $contextKey = config('audit-logger.context_session_key');
            if ($contextKey) {
                $model->context_id = session($contextKey) ?? null;
            }
            
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
     * @return BelongsTo|null
     */
    public function context(): ?BelongsTo
    {
        $contextModel = config('audit-logger.context_model');
        
        if (!$contextModel) {
            return null;
        }
        
        return $this->belongsTo($contextModel, 'context_id');
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
