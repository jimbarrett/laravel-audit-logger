<?php

namespace JimBarrett\LaravelAuditLogger;

use Illuminate\Support\ServiceProvider;
use JimBarrett\LaravelAuditLogger\Observers\AuditObserver;
use JimBarrett\LaravelAuditLogger\Services\AuditLogService;

class AuditLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__.'/../config/audit-logger.php', 'audit-logger'
        );

        // Register the service as a singleton
        $this->app->singleton(AuditLogService::class, function ($app) {
            return new AuditLogService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/audit-logger.php' => config_path('audit-logger.php'),
        ], 'audit-logger-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'audit-logger-migrations');

        // Load migrations if not published
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Auto-register observers for configured models
        $this->registerObservers();
    }

    /**
     * Register observers for configured models.
     */
    protected function registerObservers(): void
    {
        $models = config('audit-logger.observed_models', []);

        foreach ($models as $model) {
            if (class_exists($model)) {
                $model::observe(AuditObserver::class);
            }
        }
    }
}
