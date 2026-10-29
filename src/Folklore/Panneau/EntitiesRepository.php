<?php

namespace Folklore\Panneau;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Repositories\ChecksSlugs as RepositoryChecksSlugs;
use Folklore\Contracts\Repositories\Entities;
use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Repositories\Concerns\ChecksSlugs as ChecksSlugsConcern;
use Folklore\Repositories\Query\EntitiesQuery;
use Folklore\Repositories\Query\Sort as EntitiesSort;
use Folklore\Repositories\Query\SortDirection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use LogicException;
use Panneau\Contracts\Repositories\ChecksSlugs;
use Panneau\Contracts\Repositories\Searchable;
use Panneau\Contracts\ResourceRepository;
use Panneau\Query\ResourceQuery;
use Panneau\Query\Sort;

/**
 * The `ResourceRepository` of a Folklore repository: a Panneau 5 resource returns the
 * repository's contract from `repository()`, and the panneau module adapts every repository
 * with the typed query (`QueriesEntities`) with this class (docs/decisions/0018).
 *
 * - Lists go through `queryEntities()`. The list's filters are the repository's params, so
 *   its `buildQueryFromParams()` applies them; a range filter is `['from' => …, 'to' => …]`.
 *   Its sorts are the `order` param, which the repository's orderable columns allow. Its
 *   search is the `search` param, which the repository applies to its own fields.
 * - The resource's scope is the repository's query context (`withGlobalQuery()`), for lists,
 *   finds, creates and slug checks. A filter can't change a param of the scope. On create,
 *   the scope's values are given with the values, as Panneau's `EloquentRepository` fills
 *   them in.
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
class EntitiesRepository implements ChecksSlugs, ResourceRepository, Searchable
{
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
     * Creates the entity within the scope, and finds it again, as the next request will: its
     * version is then the one the next update compares.
     */
    public function create(array $values, array $scope): object
    {
        $repository = $this->scoped($scope);
        $created = $repository->create($this->valuesForCreate($values, $scope));

        return $repository->findById($created->id()) ?? throw new LogicException(sprintf(
            '%s created an entity outside the scope of its resource. The values of the scope are given to create(): use params named after the columns they fill, or set them in the resource\'s creating().',
            $this->repository::class,
        ));
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
        $this->repository->destroy($this->id($item));
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
     * The typed query of a list. Filters that name a param of the scope are dropped: the
     * params of a call take precedence over the query context, and the scope must.
     */
    protected function toEntitiesQuery(ResourceQuery $query): EntitiesQuery
    {
        return new EntitiesQuery(
            filters: array_diff_key($query->filters, $query->scope),
            search: $query->search,
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
     * The values given to the repository's `create()`: the values, and the scope's values
     * over them. A list in the scope is a constraint on what is found, not a value.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    protected function valuesForCreate(array $values, array $scope): array
    {
        return array_merge($values, array_filter(
            $scope,
            fn (mixed $value): bool => ! (is_array($value) && array_is_list($value)),
        ));
    }
}
