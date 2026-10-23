<?php

namespace Folklore\Testing;

use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Repositories\Query\EntitiesQuery;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a repository finds the entities it creates by their public id: the entity's
 * `id()` and the column the repository finds by must name the same value
 * (docs/decisions/0015). Use it in a test of each repository:
 *
 *     class BrandsRepositoryTest extends TestCase
 *     {
 *         use EntitiesRepositoryContractTests;
 *
 *         protected function repository(): Entities
 *         {
 *             return $this->app->make(Brands::class);
 *         }
 *
 *         protected function entityData(): array
 *         {
 *             return ['name' => 'Folklore'];
 *         }
 *     }
 *
 * @phpstan-require-extends TestCase
 */
trait EntitiesRepositoryContractTests
{
    /**
     * The repository under test, with its database ready.
     */
    abstract protected function repository(): Entities;

    /**
     * Data that `create()` turns into a valid entity.
     *
     * @return array<string, mixed>
     */
    abstract protected function entityData(): array;

    public function test_find_by_id_finds_the_entity_created_through_the_repository(): void
    {
        $repository = $this->repository();
        $created = $repository->create($this->entityData());

        $found = $repository->findById($created->id());

        $this->assertNotNull($found, 'findById() did not find the entity it created by its id(): the entity and its repository read different columns.');
        $this->assertSame($created->id(), $found->id());
    }

    public function test_update_and_destroy_find_the_entity_by_its_id(): void
    {
        $repository = $this->repository();
        $created = $repository->create($this->entityData());

        $this->assertNotNull($repository->update($created->id(), $this->entityData()));
        $this->assertTrue($repository->destroy($created->id()));
        $this->assertNull($repository->findById($created->id()));
    }

    public function test_the_typed_query_finds_the_entity_by_its_id(): void
    {
        $repository = $this->repository();
        if (! $repository instanceof QueriesEntities) {
            $this->markTestSkipped('The repository has no typed query.');
        }
        $created = $repository->create($this->entityData());
        $other = $repository->create($this->entityData());

        $page = $repository->queryEntities(new EntitiesQuery(ids: [$created->id()]));
        $except = $repository->queryEntities(new EntitiesQuery(ids: [$created->id(), $other->id()], except: [$other->id()]));

        $this->assertSame([$created->id()], array_map(fn ($entity) => $entity->id(), $page->items()));
        $this->assertSame([$created->id()], array_map(fn ($entity) => $entity->id(), $except->items()));
    }
}
