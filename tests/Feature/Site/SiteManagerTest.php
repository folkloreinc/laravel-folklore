<?php

namespace Folklore\Tests\Feature\Site;

use Folklore\Contracts\Site\Factory;
use Folklore\Contracts\Site\Site as SiteContract;
use Folklore\Site\Manager;
use Folklore\Site\Site;
use Folklore\Tests\TestCase;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SiteManagerTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('site.sites', [
            'blog' => ['host' => '*.example.ca', 'path' => 'blog'],
            'fr' => ['hosts' => ['example.ca', 'www.example.ca'], 'locale' => 'fr'],
            'en' => ['host' => 'example.com', 'locale' => 'en'],
            'custom' => CustomSite::class,
        ]);
        $app['config']->set('site.default', 'fr');
    }

    public function test_the_manager_is_a_singleton()
    {
        $manager = $this->app->make(Factory::class);

        $this->assertInstanceOf(Manager::class, $manager);
        $this->assertSame($manager, $this->app->make(Factory::class));
        $this->assertSame($manager, $this->app->make(Manager::class));
    }

    public function test_it_makes_the_sites_from_the_config()
    {
        $sites = $this->app->make(Factory::class)->sites();

        $this->assertSame(['blog', 'fr', 'en', 'custom'], $sites->map(fn ($site) => $site->id())->values()->all());
        $this->assertInstanceOf(Site::class, $sites->get('fr'));
        $this->assertInstanceOf(CustomSite::class, $sites->get('custom'));
    }

    public function test_it_finds_a_site_by_id()
    {
        $manager = $this->app->make(Factory::class);

        $this->assertSame('en', $manager->site('en')->get('locale'));
        $this->assertSame('custom', $manager->site('custom')->id());
    }

    public function test_it_throws_for_an_unknown_site()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Site [unknown] is not defined.');

        $this->app->make(Factory::class)->site('unknown');
    }

    public function test_it_finds_the_site_of_a_request()
    {
        $manager = $this->app->make(Factory::class);

        $this->assertSame('en', $manager->fromRequest(Request::create('https://example.com/about'))->id());
        $this->assertSame('fr', $manager->fromRequest(Request::create('https://www.example.ca/about'))->id());
        $this->assertSame('blog', $manager->fromRequest(Request::create('https://www.example.ca/blog/a-post'))->id());
        $this->assertSame('custom', $manager->fromRequest(Request::create('https://custom.test/'))->id());
    }

    public function test_it_falls_back_to_the_default_site()
    {
        $site = $this->app->make(Factory::class)->fromRequest(Request::create('https://unknown.test/'));

        $this->assertSame('fr', $site->id());
    }

    public function test_it_returns_null_without_a_match_nor_a_default_site()
    {
        $this->app['config']->set('site.default', null);

        $this->assertNull($this->app->make(Factory::class)->fromRequest(Request::create('https://unknown.test/')));
    }

    public function test_it_accepts_site_instances_and_ids_set_in_the_config()
    {
        $this->app['config']->set('site.sites', [
            new Site(['host' => 'example.ca'], 'instance'),
            ['id' => 'listed', 'host' => 'example.com'],
        ]);

        $manager = new Manager($this->app);

        $this->assertSame('instance', $manager->fromRequest(Request::create('https://example.ca/'))->id());
        $this->assertSame('listed', $manager->fromRequest(Request::create('https://example.com/'))->id());
    }
}

class CustomSite implements SiteContract
{
    public function id(): string
    {
        return 'custom';
    }

    public function matchRequest(Request $request): bool
    {
        return $request->getHost() === 'custom.test';
    }
}
