<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Contracts\Repositories\Entities;
use Folklore\Testing\EntitiesRepositoryContractTests;
use Folklore\Tests\Feature\Repositories\Fixtures\Notes;

/**
 * The contract test on a repository whose entities are found by their key.
 */
class EntitiesContractTest extends NotesTestCase
{
    use EntitiesRepositoryContractTests;

    protected function repository(): Entities
    {
        return new Notes;
    }

    protected function entityData(): array
    {
        return ['title' => 'A note'];
    }
}
