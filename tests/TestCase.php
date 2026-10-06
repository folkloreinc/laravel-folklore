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
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app->usePublicPath(__DIR__.'/fixture');
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
