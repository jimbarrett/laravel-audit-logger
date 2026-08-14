<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model to use for the user relationship in audit logs.
    |
    */
    'user_model' => env('AUDIT_LOGGER_USER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Context Model
    |--------------------------------------------------------------------------
    |
    | The model to use for multi-tenancy context (e.g., Practice, Community, Organization).
    | Set to null if you don't use multi-tenancy.
    |
    */
    'context_model' => env('AUDIT_LOGGER_CONTEXT_MODEL', null),

    /*
    |--------------------------------------------------------------------------
    | Context Session Key
    |--------------------------------------------------------------------------
    |
    | The session key where the context ID is stored (e.g., 'practice_id', 'community_id').
    | Set to null if you don't use multi-tenancy.
    |
    */
    'context_session_key' => env('AUDIT_LOGGER_CONTEXT_SESSION_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Sub-Context Model
    |--------------------------------------------------------------------------
    |
    | An optional second tenancy dimension nested inside the context (e.g. a Location
    | within a Practice, a Channel within a Workspace). Set to null if your tenancy is
    | only one level deep.
    |
    */
    'sub_context_model' => env('AUDIT_LOGGER_SUB_CONTEXT_MODEL', null),

    /*
    |--------------------------------------------------------------------------
    | Sub-Context Session Key
    |--------------------------------------------------------------------------
    |
    | The session key holding the sub-context ID (e.g. 'current_location').
    |
    | Note that the acting user and the context are only derivable during an HTTP request.
    | Seeders, queue jobs and console commands have neither an auth guard nor a session,
    | and their rows will be written anonymous and contextless unless you wrap them:
    |
    |     app(AuditContext::class)->scope($practiceId, $locationId, fn () => ..., userId: $id);
    |
    */
    'sub_context_session_key' => env('AUDIT_LOGGER_SUB_CONTEXT_SESSION_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Access Logging
    |--------------------------------------------------------------------------
    |
    | Records reads of a subject, answering "who looked at this record" alongside the
    | observer's "who changed it". Reads cannot be captured with Eloquent observers, so
    | this runs at the HTTP layer via the LogSubjectAccess middleware and direct calls
    | to AccessLogService.
    |
    | subject_model is the record type being protected (e.g. Patient). route_parameter
    | is the route binding the middleware reads it from. dedupe_minutes suppresses
    | repeat access by the same user to the same subject; set to 0 to record every hit.
    |
    */
    'access_log' => [
        'enabled' => env('AUDIT_LOGGER_ACCESS_LOG_ENABLED', true),
        'subject_model' => env('AUDIT_LOGGER_SUBJECT_MODEL', null),
        'route_parameter' => env('AUDIT_LOGGER_SUBJECT_ROUTE_PARAM', null),
        'default_access_type' => 'view',
        'dedupe_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be redacted in audit logs for security.
    |
    */
    'sensitive_fields' => [
        'password',
        'password_hash',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'api_secret',
        'access_token',
        'refresh_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom ID Fields
    |--------------------------------------------------------------------------
    |
    | Map model classes to their primary key field names if not using 'id'.
    | Example: \App\Models\CustomModel::class => 'custom_id'
    |
    */
    'custom_id_fields' => [
        // Add custom mappings here
    ],

    /*
    |--------------------------------------------------------------------------
    | Observed Models
    |--------------------------------------------------------------------------
    |
    | Models that should automatically be observed for audit logging.
    | Set to an empty array to manually register observers.
    |
    */
    'observed_models' => [
        // \App\Models\User::class,
        // \App\Models\Patient::class,
        // Add models you want to audit here
    ],

];
