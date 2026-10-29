<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Panneau\EntitiesRepository;
use Folklore\Tests\Feature\Repositories\Fixtures\Articles;
use Folklore\Tests\TestCase;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\ServiceProvider as PanneauServiceProvider;

/**
 * The panneau module registers the adapter of the repositories, and only when it is on.
 */
class PanneauModuleTest extends TestCase
{
    /**
     * @var list<string>|null
     */
    private ?array $enabled = null;

    protected function modules(): array
    {
        return $this->enabled ?? parent::modules();
    }

    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), PanneauServiceProvider::class];
    }

    public function test_the_module_registers_the_adapter_of_the_repositories()
    {
        $adapters = $this->app->make(RepositoryAdapters::class);

        $this->assertInstanceOf(EntitiesRepository::class, $adapters->resolve(new Articles));
    }

    public function test_the_module_turned_off_registers_no_adapter()
    {
        $this->enabled = [];
        $this->refreshApplication();

        $this->assertFalse($this->app->make(RepositoryAdapters::class)->supports(new Articles));
    }
}
