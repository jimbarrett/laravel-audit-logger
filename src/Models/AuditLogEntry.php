<?php

namespace JimBarrett\LaravelAuditLogger\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit Log Entry model for storing individual field changes.
 */
class AuditLogEntry extends Model
{
    use HasFactory;

    protected $table = 'model_audit_entries';

    public $timestamps = false;

    protected $fillable = [
        'model_audit_id',
        'updated_field',
        'old_value',
        'new_value',
    ];

    /**
     * Get the audit log that owns this entry.
     *
     * @return BelongsTo
     */
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'model_audit_id');
    }
}
