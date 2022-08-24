<?php

namespace Folklore;

use Illuminate\Support\ServiceProvider;
use Folklore\Console\AssetsViewCommand;
use Folklore\Http\Middleware\LocalMiddleware;

class LocalServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (!$this->app->environment('local')) {
            return;
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (!$this->app->environment('local')) {
            return;
        }

        $this->app[\Illuminate\Contracts\Http\Kernel::class]->pushMiddleware(
            LocalMiddleware::class
        );

        $this->publishes(
            [
                __DIR__ . '/migrations/' => database_path('migrations'),
            ],
            'migrations'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([AssetsViewCommand::class]);
        }
    }
}
