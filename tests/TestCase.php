<?php

namespace Folklore\Tests;

use Folklore\Image\ImageServiceProvider;
use Folklore\Mediatheque\Console\InstallCommand;
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

    /**
     * Sets the modules before the service provider registers, as a site's
     * config/site.php does: Testbench only calls getEnvironmentSetUp() after.
     *
     * @param  Application  $app
     */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        foreach (array_keys(ServiceProvider::MODULES) as $module) {
            $app['config']->set('site.modules.'.$module, in_array($module, $this->modules(), true));
        }
    }

    /**
     * The modules turned on. The 1.x tests cover every module but PubNub, whose
     * SDK isn't installed: its broadcaster is tested without the service provider.
     *
     * @return list<string>
     */
    protected function modules(): array
    {
        return array_values(array_diff(array_keys(ServiceProvider::MODULES), ['pubnub']));
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
     * Runs laravel-mediatheque 2's migrations, which a site publishes with
     * `mediatheque:install`: the media, their files and `medias_pivot`.
     */
    protected function migrateMediatheque(): void
    {
        foreach (InstallCommand::migrations() as $migration) {
            $this->artisan('migrate', ['--database' => 'testbench', '--path' => $migration, '--realpath' => true]);
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
        // laravel-mediatheque and laravel-image are only installed for the modules' tests
        return array_values(array_filter([
            ServiceProvider::class,
            \Folklore\Mediatheque\ServiceProvider::class,
            ImageServiceProvider::class,
        ], fn (string $provider) => class_exists($provider)));
    }

    protected function getPackageAliases($app)
    {
        return [];
    }
}
