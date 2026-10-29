<?php

namespace Folklore\Panneau;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Repositories\ChecksSlugs as RepositoryChecksSlugs;
use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Contracts\Repositories\QueryContextResolver;
use Folklore\Repositories\Concerns\ChecksSlugs as ChecksSlugsConcern;
use Folklore\Repositories\Query\EntitiesQuery;
use Folklore\Repositories\Query\Sort as EntitiesSort;
use Folklore\Repositories\Query\SortDirection;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use LogicException;
use Panneau\Contracts\Repositories\ChecksSlugs;
use Panneau\Contracts\ResourceRepository;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;

/**
 * The `ResourceRepository` of a Folklore repository: a Panneau 5 resource returns the
 * repository's contract from `repository()`, and the panneau module adapts every repository
 * with the typed query (`QueriesEntities`) with this class (docs/decisions/0018), or with
 * `SearchableEntitiesRepository` when the repository searches (`SearchesEntities`).
 *
 * - Lists go through `queryEntities()`. The list's filters are the repository's params, so
 *   its `buildQueryFromParams()` applies them; a range filter is `['from' => …, 'to' => …]`.
 *   A filter can't set a param of the scope or of the default resolver's context, nor one
 *   that the base repository reads itself (`RESERVED_PARAMS`). The list's sorts are the
 *   `order` param, which the repository's orderable columns allow, then the entity key.
 *   This class has no search: `SearchableEntitiesRepository` gives it to the repository.
 * - The resource's scope is the repository's query context (`withGlobalQuery()`), for lists,
 *   finds, creates and slug checks. It isn't written on create: the resource sets the values
 *   it needs in `creating()`, and a create whose entity is outside the scope is rolled back.
 *   Updates and deletes find the entity by its id within the repository's own context:
 *   Panneau found it within the scope first, and ids are unique across scopes.
 * - Items are the repository's entities. Their public id is their `id()`: the model's entity
 *   key (`HasEntityKey`), or its key (docs/decisions/0015).
 * - The version is a hash of the attributes of the entity's model as stored, for an entity
 *   that has one (`HasModel`). Otherwise it is null, which disables the `If-Match` check.
 * - Unique slugs are looked up by the repository's `slugExists()`
 *   (`Contracts\Repositories\ChecksSlugs`).
 *
 * Extend it to change any of these, and register the subclass for the repository's contract
 * with `Panneau::repositoryAdapter()`.
 */
class EntitiesRepository implements ChecksSlugs, ResourceRepository
{
    /**
     * The params that the base repository reads itself, which a filter can't set.
     */
    public const RESERVED_PARAMS = ['order', 'order_direction', 'identifier', 'offset', 'offset_paginator', 'search'];

    public function __construct(protected readonly Entities&QueriesEntities $repository) {}

    /**
     * The adapted repository.
     */
    public function repository(): Entities&QueriesEntities
    {
        return $this->repository;
    }

    /**
     * @return LengthAwarePaginator<int, Entity>
     */
    public function query(ResourceQuery $query): LengthAwarePaginator
    {
        return $this->scoped($query->scope)->queryEntities($this->toEntitiesQuery($query));
    }

    public function find(string $id, array $scope): ?object
    {
        return $this->scoped($scope)->findById($id);
    }

    /**
     * Creates the entity and finds it again within the scope, as the next request will: its
     * version is then the one the next update compares. Both run in a transaction of the
     * database connection (`connection()`), which an entity created outside its scope rolls
     * back.
     */
    public function create(array $values, array $scope): object
    {
        $repository = $this->scoped($scope);

        return $this->connection()->transaction(function () use ($repository, $values, $scope): Entity {
            $created = $repository->create($this->valuesForCreate($values, $scope));

            return $repository->findById($created->id()) ?? throw new LogicException(sprintf(
                '%s created an entity outside the scope of its resource, and nothing was saved. Set the values that put it within the scope in the resource\'s creating().',
                $this->repository::class,
            ));
        });
    }

    /**
     * Updates the entity, and finds it again, as the next request will.
     */
    public function update(object $item, array $values): object
    {
        $id = $this->id($item);
        $updated = $this->repository->update($id, $values)
            ?? throw new ModelNotFoundException(sprintf('%s has no entity "%s" to update.', $this->repository::class, $id));

        return $this->repository->findById($updated->id()) ?? $updated;
    }

    public function delete(object $item): void
    {
        $id = $this->id($item);
        if (! $this->repository->destroy($id)) {
            throw new ModelNotFoundException(sprintf('%s has no entity "%s" to delete.', $this->repository::class, $id));
        }
    }

    public function id(object $item): string
    {
        if (! $item instanceof Entity) {
            throw new InvalidArgumentException(sprintf('Expected an instance of %s, got %s.', Entity::class, $item::class));
        }

        return $item->id();
    }

    /**
     * A hash of the attributes of the entity's model as they were read or last saved, so
     * that the values a cast or an accessor computes don't change it.
     */
    public function version(object $item): ?string
    {
        if (! $item instanceof HasModel) {
            return null;
        }
        $attributes = $item->getModel()->getRawOriginal();
        ksort($attributes);

        return hash('xxh128', serialize(array_map(
            fn (mixed $value): mixed => is_scalar($value) ? (string) $value : $value,
            $attributes,
        )));
    }

    public function slugExists(string $field, string $value, ?string $locale, ?string $exceptId, array $scope, array $values): bool
    {
        $repository = $this->scoped($scope);
        if (! $repository instanceof RepositoryChecksSlugs) {
            throw new LogicException(sprintf(
                '%s can\'t tell whether a slug is taken: implement %s, with %s for a repository that extends the base repository.',
                $this->repository::class,
                RepositoryChecksSlugs::class,
                ChecksSlugsConcern::class,
            ));
        }

        return $repository->slugExists($field, $value, $locale, $exceptId, $values);
    }

    /**
     * The repository within a scope: its query context, merged with the scope's params.
     *
     * @param  array<string, mixed>  $scope
     */
    protected function scoped(array $scope): Entities&QueriesEntities
    {
        return count($scope) > 0 ? $this->repository->withGlobalQuery($scope) : $this->repository;
    }

    /**
     * The typed query of a list, without its search.
     */
    protected function toEntitiesQuery(ResourceQuery $query): EntitiesQuery
    {
        return new EntitiesQuery(
            filters: $this->filters($query),
            sorts: array_map(
                fn (Sort $sort): EntitiesSort => new EntitiesSort(
                    $sort->field,
                    $sort->descending() ? SortDirection::Descending : SortDirection::Ascending,
                ),
                $query->sorts,
            ),
            ids: $query->ids,
            except: $query->except,
            page: $query->page,
            perPage: $query->perPage,
        );
    }

    /**
     * The filters of a list, as the repository's params. The params of a call take
     * precedence over the query context, so the filters that name a param of the scope or
     * of the default resolver's context are dropped, and so are the reserved params.
     *
     * @return array<string, mixed>
     */
    protected function filters(ResourceQuery $query): array
    {
        $container = Container::getInstance();
        $context = $container->bound(QueryContextResolver::class)
            ? $container->make(QueryContextResolver::class)->resolve($this->repository)
            : [];

        return array_diff_key(
            $query->filters,
            $query->scope,
            $context,
            array_flip(self::RESERVED_PARAMS),
        );
    }

    /**
     * The values given to the repository's `create()`. The scope's params are the
     * repository's, which aren't always the columns they find by, so they aren't written:
     * the resource sets the values in `creating()`. Override this method to write them.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    protected function valuesForCreate(array $values, array $scope): array
    {
        return $values;
    }

    /**
     * The database connection whose transaction a create runs in: the default one. Override
     * it for a repository whose models use another connection.
     */
    protected function connection(): ConnectionInterface
    {
        return Container::getInstance()->make(ConnectionResolverInterface::class)->connection();
    }
}
