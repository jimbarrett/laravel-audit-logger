# Laravel Audit Logger

A comprehensive Laravel package for tracking who changed a record and who read one, with support for multi-tenancy, sensitive field redaction, and detailed change history.

Compliance regimes ask two different questions, and they cannot share an implementation:

| Question | Mechanism | Table |
|---|---|---|
| Who **changed** this record? | Eloquent observer | `model_audits` + `model_audit_entries` |
| Who **looked at** this record? | HTTP middleware / explicit calls | `subject_access_logs` |

Reads cannot be captured with model observers. Eloquent's `retrieved` event fires on every
hydration, so a record appearing in a search result would log as though someone opened it.
Access logging therefore runs at the HTTP layer, in far higher volume, with its own
deduplication.

## Features

- 🔍 **Automatic Tracking**: Observes Eloquent models for create, update, delete, and restore events
- 👁️ **Access Logging**: Records reads of protected records, with deduplication
- 🏢 **Multi-Tenancy Support**: Two levels of tenancy context (e.g. Practice and Location)
- 🧭 **Attributable Outside HTTP**: Seeders, jobs and commands can set context explicitly
- 🔒 **Security**: Automatic redaction of sensitive fields (passwords, tokens, etc.)
- 📝 **Detailed Logging**: Tracks field-level changes with old and new values
- 🎯 **Flexible Configuration**: Customize what gets logged and how
- ⚡ **Performance**: Optimized queries with proper indexing

## Installation

> **Note:** This package is hosted on GitHub and not yet published to Packagist. You must add the repository to your `composer.json` before installation.

### Step 1: Add GitHub Repository

In your project's `composer.json`, add the repository configuration:

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

This tells Composer where to find the package since it's not on Packagist.

### Step 2: Install the Package

**Option A: Using composer require**

```bash
composer require jimbarrett/laravel-audit-logger
```

**Option B: Manual installation**

Add to your `composer.json` require section:

```json
{
    "require": {
        "jimbarrett/laravel-audit-logger": "^2.0"
    }
}
```

Then run:

```bash
composer install
```

> **Version constraints:** Use `^2.0` for the current stable release. `^1.0` is the previous
> major and has no access logging; see the upgrade guide below.

### Step 3: Publish Configuration

```bash
php artisan vendor:publish --tag=audit-logger-config
```

Edit `config/audit-logger.php` to configure your multi-tenancy settings and which models to observe.

### Step 4: Run Migrations

```bash
php artisan migrate
```

This will create the `model_audits`, `model_audit_entries` and `subject_access_logs` tables.

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

## Access Logging

### Morph map (required)

Both tables store **short class names** (`Patient`, not `App\Models\Patient`), so
namespaces never end up baked into permanent audit rows. Register a morph map so those
values resolve back to classes:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    Relation::enforceMorphMap([
        'Patient' => \App\Models\Patient::class,
        'SoapNote' => \App\Models\SoapNote::class,
    ]);
}
```

The package does not call `enforceMorphMap()` itself: it is global state affecting every
morph relation in your application, so it belongs to the application. Without a map,
`$accessLog->subject` throws `UnresolvableSubjectType` rather than failing quietly.

### Middleware

Register on routes that display a protected record. It **must** run after
`SubstituteBindings`, so the route parameter arrives as a model. Placing it inside the
`web` group is enough. If it runs too early it throws `AccessLogMisconfigured` rather than
logging nothing forever.

```php
// config/audit-logger.php
'access_log' => [
    'subject_model' => \App\Models\Patient::class,
    'route_parameter' => 'patient',
    'default_access_type' => 'profile_view',
    'dedupe_minutes' => 5,
],
```

```php
Route::get('/patients/{patient}', [PatientsController::class, 'show'])
    ->middleware(LogSubjectAccess::class);

// Or override the access type per route
Route::get('/patients/{patient}/chart', [ChartController::class, 'show'])
    ->middleware(LogSubjectAccess::class.':chart_view');
```

### Direct calls

The middleware fires once per page load. For finer granularity (which tab, which panel),
call the service. Authorization is the application's job: the package records access, it
does not decide who may have it.

```php
app(AccessLogService::class)->log($patient, 'insurance');
```

### Querying

```php
// Everyone who touched this record
AccessLog::forSubject($patient)->with('user')->latest()->get();

// Everything one user looked at last week
AccessLog::where('user_id', $user->id)
    ->betweenDates(now()->subWeek(), now())
    ->get();
```

## Attribution Outside HTTP Requests

Every audit row records **who** did something and **which tenant** it belongs to. Both are
derived from the request by default: the user from the auth guard, the context from the
session. Seeders, queue jobs, console commands and migrations have neither, so their rows
land anonymous and contextless unless you say otherwise:

```php
use JimBarrett\LaravelAuditLogger\Support\AuditContext;

app(AuditContext::class)->scope($practice->id, $location->id, function () {
    Patient::factory()->count(20)->create();
}, userId: $admin->id);
```

`scope()` restores the previous state afterwards, including when the callback throws. For
a long-lived process, the setters do the same job without a closure:

```php
$context = app(AuditContext::class);

$context->override($practice->id, $location->id);   // tenant
$context->actingAs($user->id);                      // actor
// ... work ...
$context->clear();
```

User and context are tracked independently, because they vary independently: a queued job
dispatched from a request knows its user but has lost the session, while a nightly command
knows the tenant it is processing but has no user at all. Setting one never blanks the
other.

`actingAs(null)` is meaningful, not a no-op. It records work as explicitly unattributed,
which is more honest than borrowing whichever user happens to be authenticated.

> **Worth checking on an existing install:** rows written before this existed will have
> null user and context. `SELECT COUNT(*) FROM model_audits WHERE context_id IS NULL` tells
> you how much of your history is unattributable.

## Database Schema

### subject_access_logs table
- `id`: Primary key
- `subject_type`: Short class name of the accessed record (needs a morph map)
- `subject_id`: ID of the accessed record
- `user_id`: Who accessed it
- `access_type`: Application-defined label (e.g. `profile_view`, `notes`)
- `ip_address`: Request IP
- `context_id` / `sub_context_id`: Tenancy context
- `created_at`: When

No foreign keys, by design. Cascading from the subject or the user would delete the record
of who accessed what at exactly the moment it matters most.

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

## Upgrading from v1 to v2

v2 adds read auditing and fixes context attribution. Four things need attention:

1. **Run the new migrations.** They create `subject_access_logs` and add `sub_context_id`
   to `model_audits`. Both are additive; no existing data changes.
2. **Register a morph map** if you want to use `$auditLog->model` or
   `$accessLog->subject`. Existing `model_type` values are already short class names, so
   no data migration is needed. If you previously worked around the short names by string
   matching (`where('model_type', 'LIKE', '%\\'.$name)`), you can drop that now.
3. **Set user and context explicitly in seeders, jobs and console commands.** Previously
   these wrote rows with a null `user_id` and `context_id` silently. They still will
   unless wrapped in `AuditContext::scope()`. Check what you already have:
   `SELECT COUNT(*) FROM model_audits WHERE context_id IS NULL OR user_id IS NULL`.
4. **Configure `sub_context_*`** if your tenancy has two levels. Leaving them null keeps
   v1 behavior.

Applications that were logging record access by hand should move that data into
`subject_access_logs` and delete their own table. The column mapping is
`patient_id` to `subject_type` + `subject_id`, `practice_id` to `context_id`, and
`location_id` to `sub_context_id`.

## Changelog

### v2.0.0 (2026-08-14)
- 👁️ Access logging: `AccessLog`, `AccessLogService`, and the `LogSubjectAccess` middleware
- 🧭 `AuditContext` resolves the acting user and tenancy context explicitly first, request
  second, so writes from seeders, jobs and console commands are attributable
- 🏢 Second tenancy dimension via `sub_context_model` / `sub_context_session_key`, added to
  both `model_audits` and `subject_access_logs`
- 🔗 `AuditLog::model()` and `AccessLog::subject()` morph relations, requiring an
  application-registered morph map
- 💥 Breaking: `model_audits` gains `sub_context_id`; context resolution no longer reads
  the session directly; the package now requires `illuminate/http`
- 🧪 Real test coverage replacing the v1 placeholder stubs (34 tests)

### v1.0.0 (2025-10-10)
- 🎉 Initial stable release
- Automatic model observation for create, update, delete, and restore events
- Multi-tenant support with configurable context model
- Sensitive field redaction for security
- Field-level change tracking with old/new values
- Optimized database schema with proper indexing
- Comprehensive documentation and examples

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
