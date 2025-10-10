<?php

namespace JimBarrett\LaravelAuditLogger\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use JimBarrett\LaravelAuditLogger\AuditLoggerServiceProvider;

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
    }
}
