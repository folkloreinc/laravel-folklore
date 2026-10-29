<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Contracts\Repositories\ChecksSlugs;
use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueriesEntities;

/**
 * The contract a Panneau resource points to with `repository()`.
 *
 * @extends Entities<ArticleEntity>
 * @extends QueriesEntities<ArticleEntity>
 */
interface ArticlesContract extends ChecksSlugs, Entities, QueriesEntities
{
    public function findById(string $id): ?ArticleEntity;
}
