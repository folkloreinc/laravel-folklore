<?php

namespace Folklore\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->app->config->set('locale.locales', ['fr', 'en']);

        $this->app->instance('path.public', __DIR__ . '/fixture');
    }

    protected function getPackageProviders($app)
    {
        return [
            'Cviebrock\EloquentSluggable\ServiceProvider',
            'Folklore\Mediatheque\ServiceProvider',
            'Folklore\Locale\LocaleServiceProvider',
            'Folklore\ServiceProvider',
        ];
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->app->config->set('locale.locales', ['fr', 'en']);

        $this->loadMigrationsFrom(__DIR__ . '/../src/migrations');
    }
}
