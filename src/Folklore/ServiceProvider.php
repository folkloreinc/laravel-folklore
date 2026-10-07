<?php

namespace Folklore;

use Exception;
use Folklore\Broadcasters\PubNubBroadcaster;
use Folklore\Console\AssetsViewCommand;
use Folklore\Console\DaemonRestartCommand;
use Folklore\Console\EntityContractMakeCommand;
use Folklore\Console\EntityMakeCommand;
use Folklore\Console\EntityModelMakeCommand;
use Folklore\Console\PubSubHubbubSubscribe;
use Folklore\Console\PubSubHubbubUnsubscribe;
use Folklore\Console\RepositoryContractMakeCommand;
use Folklore\Console\RepositoryMakeCommand;
use Folklore\Console\UsersCreateCommand;
use Folklore\Contracts\Services\CustomerIo;
use Folklore\Contracts\Services\PubSubHubbub\Factory;
use Folklore\Http\Middleware\LocalMiddleware;
use Folklore\Mediatheque\Contracts\Models\File;
use Folklore\Models\Media;
use Folklore\Models\MediaFile;
use Folklore\Repositories\Blocks;
use Folklore\Repositories\Medias;
use Folklore\Repositories\Organisations;
use Folklore\Repositories\Pages;
use Folklore\Repositories\Users;
use Folklore\Routing\UrlGeneratorMixin;
use Folklore\Services\CustomerIo\Client;
use Folklore\Services\CustomerIo\MailTransport;
use Folklore\Services\Google\Drive;
use Folklore\Services\Google\Maps;
use Folklore\Services\Google\Places;
use Folklore\Services\PubSubHubbub\PubSubHubbubManager;
use Folklore\Site\Manager as SiteManager;
use Folklore\Support\Concerns\RegistersBindings;
use Folklore\Support\Csv;
use Folklore\Support\OffsetPaginator;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Fortify\Fortify;
use PubNub\PNConfiguration;
use PubNub\PubNub;
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

        $this->registerSite();

        if ($this->app['config']->get('services.customerio') !== null) {
            $this->registerCustomerIo();
        }

        if ($this->app['config']->get('pubsubhubbub') !== null) {
            $this->registerPubsubHubbub();
        }

        $this->registerGoogle();
    }

    protected function registerRepositories()
    {
        $this->app->bind(
            Contracts\Repositories\Users::class,
            Users::class
        );

        $this->app->bind(
            Contracts\Repositories\Medias::class,
            Medias::class
        );

        $this->app->bind(
            Contracts\Repositories\Pages::class,
            Pages::class
        );

        $this->app->bind(
            Contracts\Repositories\Blocks::class,
            Blocks::class
        );

        $this->app->bind(
            Contracts\Repositories\Organisations::class,
            Organisations::class
        );

        $repositories = $this->app['config']->get('app.repositories', []);
        foreach ($repositories as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }

    protected function registerMediatheque()
    {
        $this->app->bind(
            Mediatheque\Contracts\Models\Media::class,
            Media::class
        );

        $this->app->bind(
            File::class,
            MediaFile::class
        );
    }

    protected function registerSite()
    {
        $this->app->singleton(Contracts\Site\Factory::class, function ($app) {
            return new SiteManager($app);
        });

        $this->app->alias(Contracts\Site\Factory::class, SiteManager::class);
    }

    /**
     * Register CustomerIo
     *
     * @return void
     */
    protected function registerCustomerIo()
    {
        $this->registerBindingsFromConfig(Client::class, [
            '$key' => 'services.customerio.key',
            '$siteId' => 'services.customerio.site_id',
            '$trackingKey' => 'services.customerio.tracking_key',
            '$apiBaseUrl' => 'services.customerio.api_base_url',
            '$trackBaseUrl' => 'services.customerio.track_base_url',
            '$debug' => ['services.customerio.debug', false],
        ]);

        $this->app->singleton('services.customerio', function () {
            return $this->app->make(Client::class);
        });

        $this->app->alias('services.customerio', CustomerIo::class);
    }

    protected function registerPubsubHubbub()
    {
        $this->app->singleton('services.pubsubhubbub.manager', function ($app) {
            return new PubSubHubbubManager($app);
        });

        $this->app->bind(
            Factory::class,
            'services.pubsubhubbub.manager'
        );
    }

    /**
     * Register Google
     *
     * @return void
     */
    protected function registerGoogle()
    {
        $this->registerBindingsFromConfig(Drive::class, []);

        $this->registerBindingsFromConfig(Maps::class, [
            '$key' => 'services.google.key',
        ]);

        $this->registerBindingsFromConfig(Places::class, [
            '$key' => 'services.google.key',
        ]);

        $this->app->alias(
            Drive::class,
            Contracts\Services\Google\Drive::class
        );

        $this->app->alias(
            Places::class,
            Contracts\Services\Google\Places::class
        );

        $this->app->alias(
            Maps::class,
            Contracts\Services\Google\Maps::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootHttp();

        $this->bootAuth();

        $this->bootPubNubBroadcaster();

        $this->bootMail();

        // Console
        if ($this->app->runningInConsole()) {
            $this->commands([
                AssetsViewCommand::class,
                UsersCreateCommand::class,
                DaemonRestartCommand::class,
            ]);
        }

        if ($this->app['config']->get('pubsubhubbub') !== null && $this->app->runningInConsole()) {
            $this->commands([
                PubSubHubbubSubscribe::class,
                PubSubHubbubUnsubscribe::class,
            ]);
        }

        // Boot local environment
        if ($this->app->environment('local')) {
            $this->bootLocal();
        }
    }

    public function bootMail()
    {
        if ($this->app['config']->get('services.customerio') !== null) {
            Mail::extend('customerio', function (array $config = []) {
                return new MailTransport(
                    $this->app[CustomerIo::class],
                    $config
                );
            });
        }
    }

    public function bootHttp()
    {
        // Routing
        UrlGenerator::macro(
            'routeForReactRouter',
            $this->app->make(UrlGeneratorMixin::class)->routeForReactRouter()
        );

        // Paginator resolver
        OffsetPaginator::currentPageResolver(function ($paramName = 'offset') {
            $offset = $this->app['request']->input($paramName);

            if (filter_var($offset, FILTER_VALIDATE_INT) !== false && (int) $offset >= 0) {
                return (int) $offset;
            }

            return 0;
        });

        Response::macro('pixel', function ($status = 200) {
            return response(
                hex2bin(
                    '89504e470d0a1a0a0000000d494844520000000100000001010300000025db56ca00000003504c5445000000a77a3dda0000000174524e530040e6d8660000000a4944415408d76360000000020001e221bc330000000049454e44ae426082'
                ),
                $status
            )->header('Content-type', 'image/png');
        });

        // Values starting like a formula (=, +, -, @) are prefixed with a single
        // quote, so that a spreadsheet shows them as text instead of running
        // them, unless $escapeFormulas is false. Numbers are left as they are.
        Response::macro('csv', function (callable $getRows, $filename, bool $escapeFormulas = true) {
            try {
                $response = response()->streamDownload(
                    function () use ($getRows, $escapeFormulas) {
                        try {
                            $file = fopen('php://output', 'w+');

                            $page = 1;
                            $lastPage = 1;
                            $columns = null;

                            do {
                                $items = call_user_func($getRows, $page);

                                foreach ($items as $item) {
                                    if ($item instanceof JsonResource) {
                                        $row = json_decode(json_encode($item), true);
                                    } elseif ($item instanceof Arrayable) {
                                        $row = $item->toArray();
                                    } else {
                                        $row = $item;
                                    }
                                    if (is_null($columns)) {
                                        $columns = array_keys($row);
                                        $columns = collect($row)
                                            ->keys()
                                            ->toArray();
                                        fputcsv($file, $escapeFormulas ? Csv::escapeFormulas($columns) : $columns, escape: '\\');
                                    }
                                    fputcsv($file, $escapeFormulas ? Csv::escapeFormulas($row) : $row, escape: '\\');
                                }
                                $lastPage =
                                    $items instanceof AbstractPaginator ||
                                    ($items instanceof ResourceCollection &&
                                        $items->resource instanceof AbstractPaginator)
                                        ? $items->lastPage()
                                        : $lastPage;
                                unset($items);
                                $page += 1;
                            } while ($page <= $lastPage);
                            fclose($file);
                        } catch (Exception $e) {
                            Log::error($e);
                        }
                    },
                    $filename,
                    [
                        'Content-type' => 'text/csv; charset="utf-8"',
                        'Content-Disposition' => 'attachment; filename='.$filename,
                    ]
                );

                return $response;
            } catch (Exception $e) {
                Log::error($e);

                return response()->json(
                    ['error' => 'Failed to export CSV.'.$e->getMessage()],
                    500
                );
            }
        });
    }

    public function bootAuth()
    {
        // Auth
        $this->app['auth']->provider('repository', function ($app, $config) {
            return $this->app->make(
                $config['repository'] ?? Contracts\Repositories\Users::class
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
                // Fortify logs the user in without SessionGuard::attempt(), so
                // rehash here like attempt() does.
                if ($this->app['config']->get('hashing.rehash_on_login', true)) {
                    $provider->rehashPasswordIfRequired($user, ['password' => $request->password]);
                }

                return $user;
            }
        });

        // Fortify confirms passwords by reading the username as a property of
        // the user, which entities don't expose: check the password with the
        // user provider instead. A site can set its own callback in its
        // FortifyServiceProvider, which boots after this one.
        Fortify::confirmPasswordsUsing(function ($user, string $password) {
            return $this->app[StatefulGuard::class]
                ->getProvider()
                ->validateCredentials($user, ['password' => $password]);
        });
    }

    public function bootPubNubBroadcaster()
    {
        $this->app
            ->make(BroadcastManager::class)
            ->extend('pubnub', function ($app, $config) {
                $conf = new PNConfiguration;
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
                __DIR__.'/../migrations/' => database_path('migrations'),
            ],
            'migrations'
        );

        $this->app[Kernel::class]->pushMiddleware(
            LocalMiddleware::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                EntityMakeCommand::class,
                RepositoryContractMakeCommand::class,
                RepositoryMakeCommand::class,
                EntityContractMakeCommand::class,
                EntityModelMakeCommand::class,
            ]);
        }
    }
}
