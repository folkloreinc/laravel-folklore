<?php

namespace Folklore\Tests\Feature\Modules;

use Folklore\Contracts\Repositories\Blocks;
use Folklore\Contracts\Repositories\Medias;
use Folklore\Contracts\Repositories\Organisations;
use Folklore\Contracts\Repositories\Pages;
use Folklore\Contracts\Repositories\Users;
use Folklore\Contracts\Services\Google\Maps;
use Folklore\Contracts\Site\Factory as SiteFactory;
use Folklore\Exceptions\ModuleRequirementMissing;
use Folklore\Models\Media;
use Folklore\Models\MediaFile;
use Folklore\ServiceProvider;
use Folklore\Tests\Feature\Modules\Fixtures\SiteMedia;
use Folklore\Tests\TestCase;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Artisan;
use Laravel\Fortify\Fortify;

/**
 * A module that is on registers what 1.x registered for it.
 */
class ModulesOnTest extends TestCase
{
    /**
     * @var list<string>|null
     */
    private ?array $enabled = null;

    private ?string $siteMediaModel = null;

    protected function modules(): array
    {
        return $this->enabled ?? parent::modules();
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        if (! is_null($this->siteMediaModel)) {
            $app['config']->set('mediatheque.models.media', $this->siteMediaModel);
        }
    }

    public function test_the_config_lists_every_module()
    {
        $defaults = require __DIR__.'/../../../config/site.php';

        $this->assertSame(array_keys(ServiceProvider::MODULES), array_keys($defaults['modules']));
    }

    public function test_the_repositories_of_each_module_are_bound()
    {
        $this->assertTrue($this->app->bound(Users::class));
        $this->assertTrue($this->app->bound(Medias::class));
        $this->assertTrue($this->app->bound(Pages::class));
        $this->assertTrue($this->app->bound(Blocks::class));
        $this->assertTrue($this->app->bound(Organisations::class));
    }

    public function test_each_module_registers_its_services()
    {
        $this->assertSame(Media::class, $this->app['config']->get('mediatheque.models.media'));
        $this->assertSame(MediaFile::class, $this->app['config']->get('mediatheque.models.file'));
        $this->assertTrue($this->app->bound(SiteFactory::class));
        $this->assertTrue($this->app->bound(Maps::class));
        $this->assertTrue(ResponseFactory::hasMacro('pixel'));
        $this->assertTrue(ResponseFactory::hasMacro('csv'));
        $this->assertTrue(UrlGenerator::hasMacro('routeForReactRouter'));
        $this->assertNotNull(Fortify::$authenticateUsingCallback);

        $commands = array_keys(Artisan::all());
        $this->assertContains('assets:view', $commands);
        $this->assertContains('users:create', $commands);
    }

    public function test_the_medias_module_keeps_the_models_a_site_sets()
    {
        $this->siteMediaModel = SiteMedia::class;
        $this->refreshApplication();

        $this->assertSame(SiteMedia::class, $this->app['config']->get('mediatheque.models.media'));
        $this->assertSame(MediaFile::class, $this->app['config']->get('mediatheque.models.file'));
    }

    public function test_a_module_turned_on_alone_registers_only_its_own_services()
    {
        $this->enabled = ['pages'];
        $this->refreshApplication();

        $this->assertTrue($this->app->bound(Pages::class));
        $this->assertTrue($this->app->bound(Blocks::class));
        $this->assertFalse($this->app->bound(Users::class));
        $this->assertFalse($this->app->bound(Medias::class));
        $this->assertFalse($this->app->bound(SiteFactory::class));
    }

    public function test_a_module_whose_package_is_missing_fails_with_the_package_to_require()
    {
        $this->enabled = ['pubnub'];

        $this->expectException(ModuleRequirementMissing::class);
        $this->expectExceptionMessage('The [pubnub] module of laravel-folklore needs pubnub/pubnub');

        $this->refreshApplication();
    }
}
