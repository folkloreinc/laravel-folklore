<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Entities\Entity;
use Folklore\Repositories\Query\EntitiesQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The typed API of a repository, next to the 1.x `get($params, $page, $count)`.
 * The Panneau 5 plugin's adapter is built on it.
 *
 * @template TEntity of Entity = Entity
 */
interface QueriesEntities
{
    /**
     * A page of the entities matching the query, within the repository's query context.
     *
     * @return LengthAwarePaginator<int, TEntity>
     */
    public function query(EntitiesQuery $query): LengthAwarePaginator;

    /**
     * A copy of the repository with this context merged into every query. The repository
     * it is called on doesn't change, so a shared instance can't carry a context from one
     * job or request to the next.
     *
     * @param  array<string, mixed>  $context
     */
    public function withGlobalQuery(array $context): static;
}
