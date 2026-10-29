<?php

namespace Folklore\Testing;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Panneau\EntitiesRepository;
use Panneau\Contracts\ResourceRepository;
use Panneau\Query\ResourceQuery;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a repository works as the repository of a Panneau 5 resource, through the
 * panneau module's adapter: it finds what it creates within the resource's scope, and the
 * version of an item is the same when it is saved and when it is found next, so that the
 * `If-Match` of the next update matches. Use it in a test of each repository a resource
 * points to:
 *
 *     class ArticlesResourceRepositoryTest extends TestCase
 *     {
 *         use PanneauRepositoryContractTests;
 *
 *         protected function repository(): Entities&QueriesEntities
 *         {
 *             return $this->app->make(Articles::class);
 *         }
 *
 *         protected function createValues(): array
 *         {
 *             return ['title' => 'Folklore'];
 *         }
 *
 *         protected function updateValues(): array
 *         {
 *             return ['title' => 'Urbania'];
 *         }
 *
 *         protected function scope(): array
 *         {
 *             return ['workspace' => 'folklore'];
 *         }
 *     }
 *
 * @phpstan-require-extends TestCase
 */
trait PanneauRepositoryContractTests
{
    /**
     * The repository under test, with its database ready.
     */
    abstract protected function repository(): Entities&QueriesEntities;

    /**
     * Values of the resource's fields, as Panneau gives them to `create()`.
     *
     * @return array<string, mixed>
     */
    abstract protected function createValues(): array;

    /**
     * Values that change an item, as Panneau gives them to `update()`.
     *
     * @return array<string, mixed>
     */
    abstract protected function updateValues(): array;

    /**
     * The resource's scope.
     *
     * @return array<string, mixed>
     */
    protected function scope(): array
    {
        return [];
    }

    /**
     * The adapter of the repository. Override it when the site registers its own.
     */
    protected function adapter(): ResourceRepository
    {
        return new EntitiesRepository($this->repository());
    }

    public function test_the_adapter_finds_the_item_it_creates_within_the_scope(): void
    {
        $adapter = $this->adapter();
        $created = $adapter->create($this->createValues(), $this->scope());

        $found = $adapter->find($adapter->id($created), $this->scope());

        $this->assertNotNull($found, 'The item created within the scope is not found within it: the values of the scope don\'t set what the scope finds by.');
        $this->assertSame($adapter->id($created), $adapter->id($found));
    }

    public function test_the_version_of_a_saved_item_is_the_version_found_next(): void
    {
        $adapter = $this->adapter();
        $created = $adapter->create($this->createValues(), $this->scope());
        $found = $adapter->find($adapter->id($created), $this->scope());
        $this->assertNotNull($found);

        $this->assertSame($adapter->version($found), $adapter->version($created), 'The version of the created item is not the version of the item found next: the next update would fail its If-Match.');

        $updated = $adapter->update($found, $this->updateValues());
        $foundAgain = $adapter->find($adapter->id($updated), $this->scope());
        $this->assertNotNull($foundAgain);

        $this->assertSame($adapter->id($found), $adapter->id($updated));
        $this->assertSame($adapter->version($foundAgain), $adapter->version($updated), 'The version of the updated item is not the version of the item found next.');
        if (! is_null($adapter->version($found))) {
            $this->assertNotSame($adapter->version($found), $adapter->version($updated), 'The version did not change after an update.');
        }
    }

    public function test_the_list_finds_the_item_by_its_id_within_the_scope(): void
    {
        $adapter = $this->adapter();
        $created = $adapter->create($this->createValues(), $this->scope());
        $other = $adapter->create($this->createValues(), $this->scope());
        $ids = [$adapter->id($created), $adapter->id($other)];

        $page = $adapter->query((new ResourceQuery(scope: $this->scope()))->withIds($ids)->withExcept([$adapter->id($other)]));

        $this->assertSame([$adapter->id($created)], array_map(fn (object $item): string => $adapter->id($item), $page->items()));
    }

    public function test_the_adapter_deletes_the_item(): void
    {
        $adapter = $this->adapter();
        $created = $adapter->create($this->createValues(), $this->scope());

        $adapter->delete($created);

        $this->assertNull($adapter->find($adapter->id($created), $this->scope()));
    }

    public function test_the_items_are_the_repository_entities(): void
    {
        $created = $this->adapter()->create($this->createValues(), $this->scope());

        $this->assertInstanceOf(Entity::class, $created);
        $this->assertNotNull($this->repository()->findById($created->id()));
    }
}
