<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Folklore\Contracts\Repositories\ChecksSlugs;
use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\SearchesEntities;

/**
 * The contract a Panneau resource points to with `repository()`.
 *
 * @extends Entities<ArticleEntity>
 * @extends SearchesEntities<ArticleEntity>
 */
interface ArticlesContract extends ChecksSlugs, Entities, SearchesEntities
{
    public function findById(string $id): ?ArticleEntity;
}
