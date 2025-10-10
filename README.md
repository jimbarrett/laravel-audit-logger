# Laravel Audit Logger

A comprehensive Laravel package for tracking model changes with support for multi-tenancy, sensitive field redaction, and detailed change history.

## Features

- 🔍 **Automatic Tracking**: Observes Eloquent models for create, update, delete, and restore events
- 🏢 **Multi-Tenancy Support**: Built-in support for multi-tenant applications
- 🔒 **Security**: Automatic redaction of sensitive fields (passwords, tokens, etc.)
- 📝 **Detailed Logging**: Tracks field-level changes with old and new values
- 🎯 **Flexible Configuration**: Customize what gets logged and how
- ⚡ **Performance**: Optimized queries with proper indexing
- 🧪 **Well Tested**: Comprehensive test coverage

## Installation

### Step 1: Add Path Repository (for local development)

In your project's `composer.json`, add the path repository:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/jimbarrett/laravel-audit-logger"
        }
    ]
}
```

### Step 2: Require the Package

```bash
composer require jimbarrett/laravel-audit-logger
```

### Step 3: Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=audit-logger-config
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

## Configuration

Edit `config/audit-logger.php` to customize the package:

```php
return [
    // User model for audit relationships
    'user_model' => \App\Models\User::class,
    
    // Multi-tenancy context model (e.g., Practice, Organization)
    'context_model' => \App\Models\Practice::class,
    
    // Session key for context ID (e.g., 'practice_id')
    'context_session_key' => 'practice_id',
    
    // Fields to redact in logs
    'sensitive_fields' => [
        'password',
        'password_hash',
        'remember_token',
        // Add more as needed
    ],
    
    // Models to automatically observe
    'observed_models' => [
        \App\Models\User::class,
        \App\Models\Patient::class,
        \App\Models\Appointment::class,
        // Add models you want to audit
    ],
];
```

## Usage

### Automatic Registration (Recommended)

Add models to the `observed_models` array in `config/audit-logger.php`:

```php
'observed_models' => [
    \App\Models\User::class,
    \App\Models\Patient::class,
],
```

### Manual Registration

In your `AppServiceProvider` or `EventServiceProvider`:

```php
use JimBarrett\LaravelAuditLogger\Observers\AuditObserver;
use App\Models\Patient;

public function boot(): void
{
    Patient::observe(AuditObserver::class);
}
```

### Querying Audit Logs

```php
use JimBarrett\LaravelAuditLogger\Models\AuditLog;

// Get all audits for a specific model
$audits = AuditLog::where('model_type', 'Patient')
    ->where('model_id', $patientId)
    ->with('entries', 'user')
    ->orderBy('created_at', 'desc')
    ->get();

// Get recent changes for current context
$recentAudits = AuditLog::where('context_id', session('practice_id'))
    ->with('entries', 'user')
    ->latest()
    ->take(50)
    ->get();

// Get specific action types
$creations = AuditLog::where('action', 'created')
    ->whereBetween('created_at', [$startDate, $endDate])
    ->get();
```

### Accessing Change Details

```php
foreach ($audit->entries as $entry) {
    echo "Field: {$entry->updated_field}\n";
    echo "Old Value: {$entry->old_value}\n";
    echo "New Value: {$entry->new_value}\n";
}
```

## Multi-Tenancy Setup

### For Practice-Based Applications (Chiropractic, Medical, etc.)

```php
// config/audit-logger.php
'context_model' => \App\Models\Practice::class,
'context_session_key' => 'practice_id',
```

### For Community-Based Applications

```php
// config/audit-logger.php
'context_model' => \App\Models\Community::class,
'context_session_key' => 'active_community_id',
```

### For Organization-Based Applications

```php
// config/audit-logger.php
'context_model' => \App\Models\Organization::class,
'context_session_key' => 'organization_id',
```

## What Gets Logged

### Created Events
- All model attributes (except timestamps)
- Sensitive fields are redacted

### Updated Events
- Only changed fields
- Old and new values for each field
- Skips `updated_at` and `created_at` changes

### Deleted Events
- **Soft Delete**: Logs status change only
- **Hard Delete**: Captures all attributes before deletion

### Restored Events
- Logs when a soft-deleted record is restored

## Advanced Usage

### Custom ID Fields

For models with non-standard primary keys:

```php
// config/audit-logger.php
'custom_id_fields' => [
    \App\Models\CustomModel::class => 'custom_id',
],
```

### Additional Sensitive Fields

```php
// config/audit-logger.php
'sensitive_fields' => [
    'password',
    'ssn',
    'credit_card',
    'bank_account',
],
```

### Disable Auto-Registration

Set `observed_models` to an empty array and manually register observers:

```php
'observed_models' => [],
```

## Database Schema

### model_audits table
- `id`: Primary key
- `model_type`: Class name of the audited model
- `model_id`: ID of the audited record
- `action`: Type of change (created, updated, deleted, restored)
- `user_id`: Who made the change (nullable)
- `context_id`: Multi-tenancy context (nullable)
- `created_at`: When the change occurred

### model_audit_entries table
- `id`: Primary key
- `model_audit_id`: Foreign key to model_audits
- `updated_field`: Name of the changed field
- `old_value`: Previous value (nullable)
- `new_value`: New value (nullable)

> **Note:** The package uses `model_audits` and `model_audit_entries` table names to avoid conflicts with Laravel's default logging system which may use `audit_logs`.

## Performance Considerations

- Indexes are automatically created on frequently queried columns
- No timestamps on `audit_log_entries` for faster inserts
- Bulk inserts for multiple field changes
- Only logs actual changes (not no-op updates)

## Testing

```bash
cd packages/jimbarrett/laravel-audit-logger
composer install
vendor/bin/phpunit
```

## Troubleshooting

### Audits not appearing?
1. Check that migrations have run: `php artisan migrate:status`
2. Verify model is registered in config or manually observed
3. Check Laravel logs for errors: `storage/logs/laravel.log`

### Context ID not saving?
1. Verify `context_session_key` matches your session key
2. Ensure session has the context ID before model changes

### Too many logs?
1. Remove models from `observed_models` that don't need auditing
2. Consider archiving old audit logs periodically

## Migration to Standalone Package

When ready to extract to a separate repository:

```bash
# Create new repo
mkdir ~/laravel-audit-logger
cd ~/laravel-audit-logger
git init

# Copy package files
cp -r packages/jimbarrett/laravel-audit-logger/* .

# Update composer.json in main project
# Change from path to vcs repository:
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/jimbarrett/laravel-audit-logger"
        }
    ]
}

# Tag and push
git add .
git commit -m "Initial commit"
git tag v1.0.0
git push origin main --tags
```

## License

MIT License - see LICENSE file for details

## Credits

Created by Jim Barrett for use across multiple Laravel projects.
