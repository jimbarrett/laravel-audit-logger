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

### Step 1: Add GitHub Repository

In your project's `composer.json`, add the GitHub repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/jimbarrett/laravel-audit-logger"
        }
    ]
}
```

### Step 2: Require the Package

```bash
composer require jimbarrett/laravel-audit-logger:dev-main
```

> **Note:** Until a stable release is tagged, use `dev-main`. After tagging versions, you can use semantic versioning like `^1.0`.

### Step 3: Publish Configuration

```bash
php artisan vendor:publish --tag=audit-logger-config
```

Edit `config/audit-logger.php` to configure your multi-tenancy settings and which models to observe.

### Step 4: Run Migrations

```bash
php artisan migrate
```

This will create the `model_audits` and `model_audit_entries` tables.

## Configuration

After publishing, edit `config/audit-logger.php` to customize the package for your application.

### Basic Configuration

```php
return [
    // User model for audit relationships
    'user_model' => \App\Models\User::class,
    
    // Models to automatically observe
    'observed_models' => [
        \App\Models\User::class,
        \App\Models\Post::class,
        \App\Models\Order::class,
        // Add any models you want to track
    ],
    
    // Fields to redact in logs (sensitive data protection)
    'sensitive_fields' => [
        'password',
        'password_hash',
        'remember_token',
        'api_token',
        'api_secret',
        // Add any sensitive field names
    ],
];
```

### Multi-Tenancy Configuration (Optional)

**What is multi-tenancy support?**

Multi-tenancy allows you to scope audit logs to a specific tenant (organization, team, workspace, etc.) in your application. This is useful when:

- Multiple organizations use your application and you need to track changes per organization
- You want to filter audit logs by tenant without complex queries
- You need to ensure data isolation and compliance per tenant
- You're building dashboards showing activity within a specific tenant

**How it works:**

The package automatically captures a "context ID" from the session when changes occur. This context ID links the audit log to a specific tenant model (like Organization, Team, or Workspace).

**Configuration:**

```php
return [
    // The model representing your tenant (e.g., Organization, Team, Workspace, Practice)
    'context_model' => \App\Models\Organization::class,
    
    // The session key where your tenant ID is stored
    // This should match whatever key you use when setting the active tenant
    // Examples: 'organization_id', 'team_id', 'workspace_id', 'practice_id'
    'context_session_key' => 'organization_id',
];
```

**Example: Setting the context in your app:**

```php
// When a user logs in or switches organizations
session(['organization_id' => $organization->id]);

// Now all model changes will be linked to this organization
$post = Post::create([...]);  // Audit log will have context_id = $organization->id
```

**Querying by tenant:**

```php
// Get all audits for current organization
$audits = AuditLog::where('context_id', session('organization_id'))->get();

// Get audits across all organizations (admin view)
$allAudits = AuditLog::with('context')->get();
```

**Leave null if not needed:**

If your application is single-tenant or you don't need to scope audits by organization, set these to `null`:

```php
'context_model' => null,
'context_session_key' => null,
```

### Advanced Configuration

```php
return [
    // Custom primary key fields for models with non-standard IDs
    'custom_id_fields' => [
        \App\Models\CustomModel::class => 'custom_id',
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

## Multi-Tenancy Examples

Here are common multi-tenancy patterns and how to configure them:

### SaaS with Organizations/Companies

```php
// config/audit-logger.php
'context_model' => \App\Models\Organization::class,
'context_session_key' => 'organization_id',

// In your authentication/switching logic:
session(['organization_id' => $user->current_organization_id]);
```

### Team-Based Applications (Slack, Notion style)

```php
// config/audit-logger.php
'context_model' => \App\Models\Team::class,
'context_session_key' => 'current_team_id',

// When user switches teams:
session(['current_team_id' => $team->id]);
```

### Workspace-Based Applications

```php
// config/audit-logger.php
'context_model' => \App\Models\Workspace::class,
'context_session_key' => 'workspace_id',
```

### Multi-Location Businesses (Retail, Medical Practices, etc.)

```php
// config/audit-logger.php
'context_model' => \App\Models\Location::class,
'context_session_key' => 'current_location_id',
```

### Single-Tenant Applications

```php
// config/audit-logger.php
'context_model' => null,
'context_session_key' => null,
```

> **Tip:** You can query audits across tenants using `AuditLog::with('context')` if you need admin-level visibility.

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
composer install
vendor/bin/phpunit
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Changelog

### v1.0.0 (Unreleased)
- Initial release
- Automatic model observation
- Multi-tenant support
- Sensitive field redaction
- Field-level change tracking

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

## Versioning & Releases

This package follows [Semantic Versioning](https://semver.org/).

To create a new release:

```bash
# Tag a version
git tag v1.0.0
git push origin v1.0.0

# Update composer.json requirement in your app
"jimbarrett/laravel-audit-logger": "^1.0"
```

## License

MIT License - see LICENSE file for details

## Credits

Created by Jim Barrett for use across multiple Laravel projects.
