<?php

namespace Folklore\Tests\Feature\Repositories;

use Cviebrock\EloquentSluggable\ServiceProvider as SluggableServiceProvider;
use Folklore\Contracts\Repositories\Pages;
use Folklore\Tests\TestCase;

class PagesPublishedFilterTest extends TestCase
{
    protected Pages $pages;

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [SluggableServiceProvider::class]);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('locale.locales', ['fr', 'en']);
        $app['config']->set('app.fallback_locale', 'fr');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testbench']);
        foreach (['000000_create_pages_table', '000001_create_blocks_table', '000002_create_blocks_pivot'] as $migration) {
            $this->artisan('migrate', [
                '--database' => 'testbench',
                '--path' => __DIR__.'/../../../src/migrations/2021_01_01_'.$migration.'.php',
                '--realpath' => true,
            ]);
        }

        $this->pages = $this->app->make(Pages::class);
        $this->pages->create(['handle' => 'about', 'title' => ['fr' => 'À propos'], 'published' => true]);
        $this->pages->create(['handle' => 'draft', 'title' => ['fr' => 'Brouillon'], 'published' => false]);
    }

    public function test_pages_are_returned_whatever_their_state_by_default()
    {
        $this->assertSame(['about', 'draft'], $this->handles());
        $this->assertSame('draft', $this->pages->findBySlug('brouillon', 'fr')?->handle());
        $this->assertSame('draft', $this->pages->findByHandle('draft')?->handle());
    }

    public function test_the_published_param_filters_listings()
    {
        $this->assertSame(['about'], $this->handles(['published' => true]));
        $this->assertSame(['draft'], $this->handles(['published' => false]));
        $this->assertSame(1, $this->pages->count(['published' => true]));
        $this->assertFalse($this->pages->has(['published' => false, 'identifier' => 'about']));
    }

    public function test_the_published_param_accepts_request_values()
    {
        $this->assertSame(['about'], $this->handles(['published' => '1']));
        $this->assertSame(['about'], $this->handles(['published' => 'true']));
        $this->assertSame(['draft'], $this->handles(['published' => '0']));
        $this->assertSame(['draft'], $this->handles(['published' => 'false']));
        $this->assertSame(['about', 'draft'], $this->handles(['published' => '']));
        $this->assertSame(['about', 'draft'], $this->handles(['published' => 'unknown']));
    }

    public function test_a_global_published_param_filters_lookups()
    {
        $this->pages->setGlobalQuery(['published' => true]);

        $this->assertNull($this->pages->findBySlug('brouillon', 'fr'));
        $this->assertNull($this->pages->findByHandle('draft'));
        $this->assertSame('about', $this->pages->findBySlug('a-propos', 'fr')?->handle());
    }

    protected function handles(array $params = []): array
    {
        return $this->pages->get(array_merge(['order' => 'id'], $params))
            ->map(fn ($page) => $page->handle())
            ->all();
    }
}
