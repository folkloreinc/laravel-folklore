<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Entities\Entity;

/**
 * A repository whose typed query applies its search (`EntitiesQuery::$search`, the `search`
 * param of `buildQueryFromParams()`) to its own fields, and never ignores it. The Panneau
 * plugin offers a search only on the resources whose repository implements it.
 *
 * @template TEntity of Entity = Entity
 *
 * @extends QueriesEntities<TEntity>
 */
interface SearchesEntities extends QueriesEntities {}
