<?php

namespace Folklore\Tests;

use Folklore\ServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', $this->getTestDatabaseConnection());

        $app->usePublicPath(__DIR__.'/fixture');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Unlike SQLite in memory, a database server keeps its tables from one
        // test to the next.
        if ($this->app['config']->get('database.connections.testbench.driver') !== 'sqlite') {
            $this->artisan('db:wipe', ['--database' => 'testbench']);
        }
    }

    /**
     * SQLite in memory by default. Set DB_DRIVER to `mysql`, with DB_HOST,
     * DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD, to run the tests
     * against a MySQL server, as CI does.
     */
    protected function getTestDatabaseConnection(): array
    {
        $driver = env('DB_DRIVER', 'sqlite');
        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ];
        }

        return [
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'testing'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }

    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class, \Folklore\Mediatheque\ServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [];
    }
}
