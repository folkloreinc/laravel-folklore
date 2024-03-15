<?php

namespace Folklore;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use PubNub\PubNub;
use PubNub\PNConfiguration;
use Folklore\Broadcasters\PubNubBroadcaster;
use Folklore\Support\Concerns\RegistersBindings;
use Folklore\Support\OffsetPaginator;
use Ramsey\Uuid\Uuid;

class ServiceProvider extends BaseServiceProvider
{
    use RegistersBindings;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRepositories();

        $this->registerMediatheque();

        if ($this->app['config']->get('services.customerio') !== null) {
            $this->registerCustomerIo();
        }

        if ($this->app['config']->get('pubsubhubbub') !== null) {
            $this->registerPubsubHubbub();
        }
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
     * Register CustomerIo
     *
     * @return void
     */
    protected function registerCustomerIo()
    {
        $this->registerBindingsFromConfig(\Folklore\Services\CustomerIo\Client::class, [
            '$key' => 'services.customerio.key',
            '$siteId' => 'services.customerio.site_id',
            '$trackingKey' => 'services.customerio.tracking_key',
        ]);

        $this->app->singleton('services.customerio', function () {
            return $this->app->make(\Folklore\Services\CustomerIo\Client::class);
        });

        $this->app->alias('services.customerio', \Folklore\Contracts\Services\CustomerIo::class);
    }

    protected function registerPubsubHubbub()
    {
        $this->app->singleton('services.pubsubhubbub.manager', function ($app) {
            return new \Folklore\Services\PubSubHubbub\PubSubHubbubManager($app);
        });

        $this->app->bind(
            \Folklore\Contracts\Services\PubSubHubbub\Factory::class,
            'services.pubsubhubbub.manager'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootRequest();

        $this->bootAuth();

        $this->bootPubNubBroadcaster();

        // Console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Folklore\Console\UsersCreateCommand::class,
                \Folklore\Console\DaemonRestartCommand::class,
            ]);
        }

        if ($this->app['config']->get('pubsubhubbub') !== null && $this->app->runningInConsole()) {
            $this->commands([
                \Folklore\Console\PubSubHubbubSubscribe::class,
                \Folklore\Console\PubSubHubbubUnsubscribe::class,
            ]);
        }

        // Boot local environment
        if ($this->app->environment('local')) {
            $this->bootLocal();
        }
    }

    public function bootRequest()
    {
        // Routing
        \Illuminate\Routing\UrlGenerator::macro(
            'routeForReactRouter',
            $this->app->make(\Folklore\Routing\UrlGeneratorMixin::class)->routeForReactRouter()
        );

        // Paginator resolver
        OffsetPaginator::currentPageResolver(function ($paramName = 'offset') {
            $offset = $this->app['request']->input($paramName);

            if (filter_var($offset, FILTER_VALIDATE_INT) !== false && (int) $offset >= 0) {
                return (int) $offset;
            }

            return 0;
        });
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
            $provider = $this->app[StatefulGuard::class]->getProvider();
            $user = $provider->retrieveByCredentials([
                Fortify::username() => $request->{Fortify::username()},
            ]);

            if (
                $user &&
                $provider->validateCredentials($user, ['password' => $request->password])
            ) {
                return $user;
            }
        });
    }

    public function bootPubNubBroadcaster()
    {
        $this->app
            ->make(\Illuminate\Broadcasting\BroadcastManager::class)
            ->extend('pubnub', function ($app, $config) {
                $conf = new PNConfiguration();
                $conf->setUuid(Uuid::uuid4()->toString());
                $conf->setSubscribeKey(
                    data_get(
                        $config,
                        'subscribe_key',
                        $this->app['config']->get('services.pubnub.subscribe_key')
                    )
                );
                $conf->setPublishKey(
                    data_get(
                        $config,
                        'publish_key',
                        $this->app['config']->get('services.pubnub.publish_key')
                    )
                );
                $pubnub = new PubNub($conf);
                return new PubNubBroadcaster(
                    $pubnub,
                    data_get(
                        $config,
                        'namespace',
                        $this->app['config']->get('services.pubnub.namespace')
                    ),
                    $config
                );
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
