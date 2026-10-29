<?php

namespace Folklore\Panneau;

use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Contracts\Repositories\SearchesEntities;
use Folklore\Repositories\Query\EntitiesQuery;
use Panneau\Contracts\Repositories\Searchable;
use Panneau\Query\ResourceQuery;

/**
 * The adapter of a repository that searches (`SearchesEntities`): Panneau's search is given
 * to its typed query, which applies it to the repository's own fields. The panneau module
 * registers it for those repositories, so that Panneau only offers a search that isn't
 * ignored.
 */
class SearchableEntitiesRepository extends EntitiesRepository implements Searchable
{
    public function __construct(Entities&QueriesEntities&SearchesEntities $repository)
    {
        parent::__construct($repository);
    }

    protected function toEntitiesQuery(ResourceQuery $query): EntitiesQuery
    {
        return parent::toEntitiesQuery($query)->withSearch($query->search);
    }
}
