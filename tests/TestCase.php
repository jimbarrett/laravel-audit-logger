<?php

namespace JimBarrett\LaravelAuditLogger\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JimBarrett\LaravelAuditLogger\AuditLoggerServiceProvider;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestPatient;
use JimBarrett\LaravelAuditLogger\Tests\Fixtures\TestUser;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            AuditLoggerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('audit-logger.user_model', TestUser::class);
        $app['config']->set('audit-logger.context_session_key', 'practice_id');
        $app['config']->set('audit-logger.sub_context_session_key', 'current_location');
        // Set as a whole array: mergeConfigFrom is a shallow merge, so a dotted set of a
        // single nested key would replace the package's entire access_log block.
        $app['config']->set('audit-logger.access_log', [
            'enabled' => true,
            'subject_model' => TestPatient::class,
            'route_parameter' => 'patient',
            'default_access_type' => 'view',
            'dedupe_minutes' => 5,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
        $this->createFixtureTables();
    }

    /**
     * Tables backing the fixture models. Kept out of the package migrations so they
     * never reach a consuming application.
     */
    protected function createFixtureTables(): void
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('test_soft_deleting_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->softDeletes();
        });

        Schema::create('test_patients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
        });
    }
}
