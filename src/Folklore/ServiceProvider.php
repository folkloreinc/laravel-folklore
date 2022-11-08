<?php

namespace Folklore;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Http\Request;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRepositories();

        $this->registerMediatheque();
    }

    protected function registerRepositories()
    {
        $this->app->bind(
            \Folklore\Contracts\Repositories\Users::class,
            \Folklore\Repositories\Users::class
        );

        $this->app->bind(
            \Folklore\Contracts\Repositories\Medias::class,
            \Folklore\Repositories\Medias::class
        );

        $this->app->bind(
            \Folklore\Contracts\Repositories\Pages::class,
            \Folklore\Repositories\Pages::class
        );

        $this->app->bind(
            \Folklore\Contracts\Repositories\Blocks::class,
            \Folklore\Repositories\Blocks::class
        );

        $this->app->bind(
            \Folklore\Contracts\Repositories\Organisations::class,
            \Folklore\Repositories\Organisations::class
        );
    }

    protected function registerMediatheque()
    {
        $this->app->bind(
            \Folklore\Mediatheque\Contracts\Models\Media::class,
            \Folklore\Models\Media::class
        );

        $this->app->bind(
            \Folklore\Mediatheque\Contracts\Models\File::class,
            \Folklore\Models\MediaFile::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Routing
        \Illuminate\Routing\UrlGenerator::macro(
            'routeForReactRouter',
            $this->app->make(\Folklore\Routing\UrlGeneratorMixin::class)->routeForReactRouter()
        );

        $this->bootAuth();

        // Console
        if ($this->app->runningInConsole()) {
            $this->commands([\Folklore\Console\UsersCreateCommand::class]);
        }

        // Boot local environment
        if ($this->app->environment('local')) {
            $this->bootLocal();
        }
    }

    public function bootAuth()
    {
        // Auth
        $this->app['auth']->provider('repository', function ($app, $config) {
            return $this->app->make(
                $config['repository'] ?? \Folklore\Contracts\Repositories\Users::class
            );
        });

        Fortify::authenticateUsing(function (Request $request) {
            $provider = $this->app['auth']->guard()->getProvider();
            $user = $provider->retrieveByCredentials([
                Fortify::username() => $request->{Fortify::username()},
            ]);

            if (
                !$user ||
                !$provider->validateCredentials($user, ['password' => $request->password])
            ) {
                return $user;
            }
        });
    }

    public function bootLocal()
    {
        // Publishes
        $this->publishes(
            [
                __DIR__ . '/../migrations/' => database_path('migrations'),
            ],
            'migrations'
        );

        $this->app[\Illuminate\Contracts\Http\Kernel::class]->pushMiddleware(
            \Folklore\Http\Middleware\LocalMiddleware::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Folklore\Console\AssetsViewCommand::class,
                \Folklore\Console\ResourceFullMakeCommand::class,
                \Folklore\Console\RepositoryContractMakeCommand::class,
                \Folklore\Console\RepositoryMakeCommand::class,
                \Folklore\Console\ResourceContractMakeCommand::class,
                \Folklore\Console\ResourceModelMakeCommand::class,
            ]);
        }
    }
}
