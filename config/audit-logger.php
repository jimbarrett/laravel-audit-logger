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
