<?php

namespace Folklore\Tests\Feature\Modules;

use Folklore\Contracts\Repositories\Blocks;
use Folklore\Contracts\Repositories\Medias;
use Folklore\Contracts\Repositories\Organisations;
use Folklore\Contracts\Repositories\Pages;
use Folklore\Contracts\Repositories\Users;
use Folklore\Contracts\Services\Google\Maps;
use Folklore\Contracts\Site\Factory as SiteFactory;
use Folklore\Http\Middleware\LocalMiddleware;
use Folklore\Mediatheque\Contracts\Models\Media as MediaContract;
use Folklore\Models\Media;
use Folklore\Repositories\Pages as PagesRepository;
use Folklore\ServiceProvider;
use Folklore\Tests\TestCase;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;
use InvalidArgumentException;
use Laravel\Fortify\Fortify;

/**
 * A site that turns every module off gets the core only: no binding, macro,
 * middleware, auth provider, global override or module command
 * (docs/decisions/0002).
 */
class ModulesOffTest extends TestCase
{
    /**
     * @var array<class-string, class-string>
     */
    private array $repositories = [];

    protected function setUp(): void
    {
        // Macros and Fortify's callbacks are static: forget what the tests that
        // ran before registered.
        ResponseFactory::flushMacros();
        UrlGenerator::flushMacros();
        Fortify::$authenticateUsingCallback = null;
        Fortify::$confirmPasswordsUsingCallback = null;

        parent::setUp();
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        // The local environment, where 1.x pushed a global middleware
        $app->detectEnvironment(fn () => 'local');
        $app['config']->set('app.repositories', $this->repositories);
    }

    protected function modules(): array
    {
        return [];
    }

    public function test_the_modules_are_off_by_default()
    {
        $defaults = require __DIR__.'/../../../config/site.php';

        $this->assertSame([], array_keys(array_filter($defaults['modules'])));
    }

    public function test_no_module_repository_is_bound()
    {
        foreach ([Users::class, Medias::class, Pages::class, Blocks::class, Organisations::class] as $contract) {
            $this->assertFalse($this->app->bound($contract), "{$contract} is bound.");
        }
    }

    public function test_the_repositories_of_the_application_are_still_bound()
    {
        $this->repositories = [Pages::class => PagesRepository::class];
        $this->refreshApplication();

        $this->assertTrue($this->app->bound(Pages::class));
        $this->assertFalse($this->app->bound(Blocks::class));
    }

    public function test_the_mediatheque_models_are_not_rebound()
    {
        // Registered again, after laravel-mediatheque's provider, which binds its own model
        $this->app->register(ServiceProvider::class, true);

        $this->assertNotInstanceOf(Media::class, $this->app->make(MediaContract::class));
    }

    public function test_no_site_manager_and_no_google_service_is_bound()
    {
        $this->assertFalse($this->app->bound(SiteFactory::class));
        $this->assertFalse($this->app->bound(Maps::class));
    }

    public function test_no_macro_is_registered()
    {
        $this->assertFalse(ResponseFactory::hasMacro('pixel'));
        $this->assertFalse(ResponseFactory::hasMacro('csv'));
        $this->assertFalse(UrlGenerator::hasMacro('routeForReactRouter'));
    }

    public function test_the_page_resolver_of_paginators_is_laravel_s()
    {
        $this->app['request']->query->set('page', 'not-a-page');

        $this->assertSame(1, Paginator::resolveCurrentPage());
    }

    public function test_no_auth_provider_and_no_fortify_callback_is_registered()
    {
        $this->app['config']->set('auth.providers.users', ['driver' => 'repository']);

        $this->assertNull(Fortify::$authenticateUsingCallback);
        $this->assertNull(Fortify::$confirmPasswordsUsingCallback);
        $this->expectException(InvalidArgumentException::class);

        $this->app['auth']->createUserProvider('users');
    }

    public function test_no_broadcast_driver_is_registered()
    {
        $this->app['config']->set('broadcasting.connections.pubnub', ['driver' => 'pubnub']);

        $this->expectException(InvalidArgumentException::class);

        Broadcast::connection('pubnub');
    }

    public function test_no_global_middleware_is_pushed_in_the_local_environment()
    {
        $this->assertTrue($this->app->environment('local'));
        $this->assertFalse($this->app[Kernel::class]->hasMiddleware(LocalMiddleware::class));
    }

    public function test_only_the_core_commands_are_registered()
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('daemon:restart', $commands);
        $this->assertContains('make:entity', $commands);
        $this->assertNotContains('assets:view', $commands);
        $this->assertNotContains('users:create', $commands);
    }
}
