<?php

namespace Folklore\Panneau;

use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Contracts\Repositories\SearchesEntities;
use Illuminate\Support\ServiceProvider;
use Panneau\Repositories\RepositoryAdapters;

/**
 * The panneau module: the Panneau 5 plugin, registered by the package's service provider
 * when `site.modules.panneau` is on (docs/decisions/0018).
 */
class PanneauServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockTypes::class);

        // When Panneau first needs its adapters, so that a site's own adapters, registered
        // after, take precedence
        $this->callAfterResolving(RepositoryAdapters::class, function (RepositoryAdapters $adapters): void {
            $adapters->register(QueriesEntities::class, EntitiesRepository::class);
            // Registered after, so it wins for the repositories that search
            $adapters->register(SearchesEntities::class, SearchableEntitiesRepository::class);
        });
    }
}
