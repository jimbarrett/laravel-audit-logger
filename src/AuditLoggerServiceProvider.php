<?php

namespace JimBarrett\LaravelAuditLogger;

use Illuminate\Support\ServiceProvider;
use JimBarrett\LaravelAuditLogger\Observers\AuditObserver;
use JimBarrett\LaravelAuditLogger\Services\AccessLogService;
use JimBarrett\LaravelAuditLogger\Services\AuditLogService;
use JimBarrett\LaravelAuditLogger\Support\AuditContext;

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

        // Register the services as singletons
        $this->app->singleton(AuditLogService::class, function ($app) {
            return new AuditLogService();
        });

        // Singleton so that an explicit context set in a job or seeder holds for every
        // write in that process until it is cleared.
        $this->app->singleton(AuditContext::class, function ($app) {
            return new AuditContext();
        });

        $this->app->singleton(AccessLogService::class, function ($app) {
            return new AccessLogService($app->make(AuditContext::class));
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
