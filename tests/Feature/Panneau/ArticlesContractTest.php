<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Testing\PanneauRepositoryContractTests;
use Folklore\Tests\Feature\Repositories\Fixtures\Articles;

/**
 * The contract tests of the Panneau adapter, on a repository found by uid and scoped to a
 * workspace.
 */
class ArticlesContractTest extends PanneauTestCase
{
    use PanneauRepositoryContractTests;

    protected function repository(): Entities&QueriesEntities
    {
        return new Articles;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createValues(): array
    {
        return ['title' => 'Created', 'slug' => ['fr' => 'cree', 'en' => 'created'], 'status' => 'draft'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function updateValues(): array
    {
        return ['title' => 'Updated', 'status' => 'published'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function scope(): array
    {
        return ['workspace' => 'folklore'];
    }
}
