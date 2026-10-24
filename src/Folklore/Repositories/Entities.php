<?php

namespace Folklore\Repositories;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\HasEntityKey;
use Folklore\Contracts\Repositories\Entities as EntitiesContract;
use Folklore\Contracts\Repositories\QueriesEntities;
use Folklore\Contracts\Repositories\QueryContextResolver;
use Folklore\JsonData\JsonData;
use Folklore\JsonData\JsonDataCast;
use Folklore\Repositories\Query\EntitiesQuery;
use Folklore\Repositories\Query\Sort;
use Folklore\Support\OffsetPaginator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Scout\Builder as ScoutBuilder;
use LogicException;

/**
 * The base of the repositories of entities over Eloquent models.
 *
 * Its templates let a repository say its model and entity (`@extends Entities<Brand,
 * BrandEntity>`), which it passes to its contracts: a repository contract that extends
 * `Entities<BrandEntity>` and this class agree. The entities come from
 * `getEntityFromModel()`; the other 1.x methods return untyped values.
 *
 * @template TModel of Model = Model
 * @template TEntity of Entity = Entity
 *
 * @implements EntitiesContract<TEntity>
 * @implements QueriesEntities<TEntity>
 */
abstract class Entities implements EntitiesContract, QueriesEntities
{
    protected $globalQuery = [];

    /**
     * The JSON attribute that the data which isn't fillable goes into
     * (`$jsonAttributeFillable`).
     *
     * @deprecated List the JSON attributes in `$jsonAttributes`; the first one is this one.
     */
    protected $jsonAttributeName = 'data';

    /**
     * The JSON attributes of the model that updates merge into, instead of replacing them:
     * `['data', 'images']`. Null: only `$jsonAttributeName`. The first one takes the data
     * that isn't fillable, as `$jsonAttributeName` does.
     *
     * @var list<string>|null
     */
    protected $jsonAttributes = null;

    /**
     * Whether the references of the JSON attributes of each result are resolved together
     * (`withJsonReferences()`).
     *
     * @var bool
     */
    protected $loadsJsonReferences = false;

    protected $jsonAttributeFillable = null;

    protected $jsonAttributeExclude = null;

    protected $queryColumns = [];

    /**
     * Columns that the `order` param accepts (see getOrderableColumns()), or
     * null to accept every column but the model's hidden attributes.
     *
     * @var array<string>|null
     */
    protected $orderableColumns = null;

    protected $identifierHandleColumn = 'handle';

    /**
     * @return TModel
     */
    abstract protected function newModel(): Model;

    protected function newQuery()
    {
        return $this->newModel()->newQuery();
    }

    /**
     * The entity whose public id is `$id`: the model's entity key (`HasEntityKey`), or its
     * key, within the repository's query context (docs/decisions/0015, 0017).
     *
     * @return TEntity|null
     */
    public function findById(string $id): ?Entity
    {
        $model = $this->findModelById($id);

        return $this->getEntityFromModel($model);
    }

    /**
     * A page of the entities matching a typed query, within the repository's query
     * context. Its filters are the repository's params, so `buildQueryFromParams()`
     * applies; its ids and exclusions are entity keys.
     *
     * @return LengthAwarePaginator<int, TEntity>
     */
    public function queryEntities(EntitiesQuery $query): LengthAwarePaginator
    {
        $builder = $this->newQueryWithParams($this->getParamsFromQuery($query));
        if (! $builder instanceof Builder) {
            throw new LogicException(static::class.'::buildQueryFromParams() must return an Eloquent query to be queried with queryEntities().');
        }
        $column = $this->getEntityKeyName();
        if (! is_null($query->ids)) {
            $builder->whereIn($column, $query->ids);
        }
        if (count($query->except) > 0) {
            $builder->whereNotIn($column, $query->except);
        }

        $models = $builder->paginate($query->perPage, ['*'], 'page', $query->page);
        if ($this->loadsJsonReferences) {
            JsonData::load($models->getCollection());
        }
        $entities = [];
        foreach ($models->getCollection() as $model) {
            $entity = $this->getEntityFromModel($model);
            if ($entity instanceof Entity) {
                $entities[] = $entity;
            }
        }
        $models->setCollection(new Collection($entities));

        return $models;
    }

    /**
     * A copy of the repository with this context merged into every query, finds included.
     * The repository it is called on doesn't change (docs/decisions/0004).
     *
     * @param  array<string, mixed>  $context
     */
    public function withGlobalQuery(array $context): static
    {
        $repository = clone $this;
        $repository->globalQuery = array_merge(
            is_array($this->globalQuery) ? $this->globalQuery : [],
            $context,
        );

        return $repository;
    }

    /**
     * A copy of the repository that resolves the references in the JSON attributes of its
     * results together, with one query per key for a whole page (`JsonData::load()`).
     */
    public function withJsonReferences(): static
    {
        $repository = clone $this;
        $repository->loadsJsonReferences = true;

        return $repository;
    }

    public function get(array $params = [], ?int $page = null, ?int $count = null)
    {
        $query = $this->newQueryWithParams($params);

        return $this->getFromQuery($query, $page, $count, $params);
    }

    public function count(array $params = []): int
    {
        $query = $this->newQueryWithParams($params);

        return $query->count();
    }

    public function has(array $params = []): bool
    {
        $query = $this->newQueryWithParams($params);

        return $query->exists();
    }

    public function pluck($column, array $params = [], ?int $page = null, ?int $count = null)
    {
        $query = $this->newQueryWithParams($params);
        if (
            ! is_null($page) &&
            isset($params['offset_paginator']) &&
            $params['offset_paginator'] === true
        ) {
            $models = new OffsetPaginator(
                $query
                    ->skip($page)
                    ->take($count)
                    ->pluck($column),
                $query->toBase()->getCountForPagination(),
                $page
            );
        } elseif (! is_null($page)) {
            $models =
                $query instanceof ScoutBuilder
                    ? $query->paginate($count, 'page', $page)
                    : $query->paginate($count, [$column], 'page', $page);
            $models->setCollection($models->toBase()->pluck($column));
        } else {
            $models = $query->take($count)->pluck($column);
        }

        return $models;
    }

    public function valueById($id, $column)
    {
        if (! is_string($column) && ! $column instanceof Expression) {
            throw new InvalidArgumentException('The column must be a string or an expression.');
        }

        return $this->newQueryInContext()
            ->where($this->getEntityKeyName(), $id)
            ->value($column);
    }

    public function value($column, array $params = [])
    {
        return $this->newQueryWithParams($params)->value($column);
    }

    protected function getFromQuery(
        $query,
        ?int $page = null,
        ?int $count = null,
        array $params = []
    ) {
        if (
            ! is_null($page) &&
            isset($params['offset_paginator']) &&
            $params['offset_paginator'] === true
        ) {
            $models = new OffsetPaginator(
                $query
                    ->skip($page)
                    ->take($count)
                    ->get(),
                $query->toBase()->getCountForPagination(),
                $page
            );
        } elseif (! is_null($page)) {
            $models =
                $query instanceof ScoutBuilder
                    ? $query->paginate($count, 'page', $page)
                    : $query->paginate($count, ['*'], 'page', $page);
        } else {
            $models = $query->take($count)->get();
        }

        if ($this->loadsJsonReferences) {
            $loaded = $models instanceof AbstractPaginator ? $models->getCollection() : $models;
            if (is_iterable($loaded)) {
                JsonData::load($loaded);
            }
        }

        $collection = $this->getEntitiesFromModels($models);

        if ($models instanceof AbstractPaginator) {
            $models->setCollection($collection);

            return $models;
        }

        return $collection;
    }

    /**
     * @return TEntity
     */
    public function create($data): Entity
    {
        $model = $this->newModel();
        $this->saveDataInTransaction($model, $data);
        $entity = $this->getEntityFromModel($model);
        if (is_null($entity)) {
            throw new LogicException(static::class.' made no entity of the model it created.');
        }

        return $entity;
    }

    /**
     * @return TEntity|null
     */
    public function update(string $id, $data): ?Entity
    {
        $model = $this->findModelById($id);
        if (! $model instanceof Model) {
            return null;
        }
        $this->saveDataInTransaction($model, $data);

        return $this->getEntityFromModel($model);
    }

    public function destroy(string $id): bool
    {
        $model = $this->findModelById($id);
        if (is_null($model)) {
            return false;
        }
        $model->delete();

        return true;
    }

    /**
     * The model whose entity key is `$id`, within the repository's query context, with the
     * given params. The default params don't apply: they shape lists, not finds.
     */
    protected function findModelById($id, $params = null)
    {
        return $this->newQueryInContext(is_array($params) ? array_filter($params, 'is_string', ARRAY_FILTER_USE_KEY) : [])
            ->where($this->getEntityKeyName(), $id)
            ->first();
    }

    /**
     * The query of the model within the repository's query context: the default resolver's
     * context, then the global query, then the given params.
     *
     * @param  array<string, mixed>  $params
     * @return Builder<TModel>
     */
    protected function newQueryInContext(array $params = []): Builder
    {
        $query = $this->buildQueryFromParams(
            $this->newQuery(),
            array_merge($this->getContextParams(), $params),
        );
        if (! $query instanceof Builder) {
            throw new LogicException(static::class.'::buildQueryFromParams() must return an Eloquent query.');
        }

        return $query;
    }

    /**
     * The column that holds the public id of the entities: the model's entity key
     * (`HasEntityKey::getEntityKeyName()`), or its key (docs/decisions/0015).
     */
    protected function getEntityKeyName(): string
    {
        $model = $this->newModel();

        return $model instanceof HasEntityKey ? $model->getEntityKeyName() : $model->getKeyName();
    }

    /**
     * The repository's params for a typed query: its filters, `search`, and `order` from
     * its sorts.
     *
     * @return array<string, mixed>
     */
    protected function getParamsFromQuery(EntitiesQuery $query): array
    {
        $params = $query->filters;
        if (! is_null($query->search)) {
            $params['search'] = $query->search;
        }
        if (count($query->sorts) > 0) {
            $params['order'] = array_map(
                fn (Sort $sort) => [$sort->field, $sort->direction->value],
                $query->sorts,
            );
        }

        return $params;
    }

    /**
     * The repository's query context: the default resolver's (`QueryContextResolver`, bound
     * in the container), then the global query (`setGlobalQuery()`, `withGlobalQuery()`).
     *
     * @return array<string, mixed>
     */
    protected function getContextParams(): array
    {
        $container = Container::getInstance();
        $resolved = $container->bound(QueryContextResolver::class)
            ? $container->make(QueryContextResolver::class)->resolve($this)
            : [];
        $global = is_array($this->globalQuery) ? $this->globalQuery : [];

        return array_filter(array_merge($resolved, $global), 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Saves the data in a database transaction: a save that fails after the model's
     * `saving` event rolls back what it created, such as the media that a JSON reference
     * ingested (docs/decisions/0013).
     */
    protected function saveDataInTransaction(Model $model, mixed $data): void
    {
        $model->getConnection()->transaction(function () use ($model, $data) {
            $this->saveData($model, $data);
        });
    }

    protected function saveData($model, $data)
    {
        // The other JSON attributes are merged into, not filled
        $this->fillModel($model, is_array($data) ? Arr::except($data, $this->getMergedJsonAttributes()) : $data);
        $this->fillModelJsonAttributes($model, $data);
        $model->save();
        $this->syncRelations($model, $data);
    }

    protected function fillModel($model, $data)
    {
        $model->fill($data);
    }

    protected function fillModelJsonAttributes($model, $data)
    {
        if ($model instanceof Model && is_array($data) && $this->hasJsonDataSchema($model)) {
            $this->fillJsonDataSchemas($model, $data);

            return;
        }

        if ($model instanceof Model && is_array($data)) {
            $this->mergeJsonAttributes($model, $data);
            // The other JSON attributes are merged into themselves, not into this one
            $data = Arr::except($data, $this->getMergedJsonAttributes());
        }

        $jsonAttributeFillable = $this->getJsonAttributeFillable();
        if (is_null($jsonAttributeFillable)) {
            return;
        }

        $jsonAttributeName = $this->getJsonAttributeName();
        $jsonAttributeExclude = $this->getJsonAttributeExclude() ?? [];
        $currentAttributeValue = JsonDataCast::getValueWithUnresolvedReferences($model, $jsonAttributeName);
        $fillable = $model->getFillable();
        $newAttributeValue = collect(
            $jsonAttributeFillable === '*'
                ? array_diff(array_keys($data), $fillable, $jsonAttributeExclude)
                : array_diff(
                    array_intersect(array_keys($data), $jsonAttributeFillable),
                    $jsonAttributeExclude
                )
        )->reduce(function ($newValue, $path, $field) use ($data) {
            if (is_numeric($field)) {
                $field = $path;
            }
            $fieldValue = data_get($data, $field);
            if (isset($fieldValue) && $path === '*') {
                $newValue = array_merge($newValue ?? [], $fieldValue ?? []);
            } else {
                $fieldValue = data_get($data, $field, data_get($newValue, $path));
                data_set($newValue, $path, $fieldValue);
            }

            return $newValue;
        }, $currentAttributeValue ?? []);
        $model->{$jsonAttributeName} = ! empty($newAttributeValue) ? $newAttributeValue : null;
    }

    /**
     * Whether one of the model's JSON attributes has a schema (`HasJsonDataSchema`): its
     * JSON attributes are then filled with `JsonData::fill()`, and `$jsonAttributeFillable`
     * and `$jsonAttributeExclude` don't apply (docs/json-data.md, "Repositories").
     */
    protected function hasJsonDataSchema(Model $model): bool
    {
        foreach ($model->getCasts() as $attribute => $cast) {
            if (is_a($cast, JsonDataCast::class, true) && ! is_null(JsonData::schema($model, (string) $attribute))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fills the JSON attributes of a model that has a schema: `JsonData::fill()` puts the
     * flat input into the attributes that have one, and refuses the keys that are neither
     * fillable nor in a schema when its root is `additional(false)`. The other attributes of
     * `$jsonAttributes` are given as a whole and merged, as without a schema.
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function fillJsonDataSchemas(Model $model, array $data): void
    {
        $merged = array_values(array_filter(
            $this->getMergedJsonAttributes(),
            fn (string $attribute) => is_null(JsonData::schema($model, $attribute)),
        ));
        $this->mergeJsonAttributes($model, Arr::only($data, $merged));
        JsonData::fill($model, Arr::except($data, $merged));
    }

    /**
     * Runs after saveData() saved the model. The cast has already synced the relations of
     * its JSON references, when the model was saved (docs/json-data.md, "Syncing"): this
     * is a hook for a repository's own relations.
     */
    protected function syncRelations($model, $data)
    {
        //
    }

    protected function newQueryWithParams($params = [])
    {
        return $this->buildQueryFromParams($this->newQuery(), $this->getQueryParams(is_array($params) ? $params : []));
    }

    protected function getDefaultParams(): array
    {
        return [];
    }

    protected function getQueryParams(array $params): array
    {
        return array_merge($this->getDefaultParams(), $this->getContextParams(), $params);
    }

    public function setGlobalQuery(array $query)
    {
        $this->globalQuery = $query;

        return $this;
    }

    protected function buildQueryFromParams($query, $params)
    {
        if (isset($params['offset'])) {
            $query->skip($params['offset']);
        }

        $query = collect($this->queryColumns ?? [])->reduce(function ($query, $column, $param) use (
            $params
        ) {
            if (is_numeric($param)) {
                $param = $column;
            }

            return collect([
                $param,
                'or_'.$param,
                'exclude_'.$param,
                'or_exclude_'.$param,
            ])->reduce(function ($query, $paramName) use ($params, $column) {
                $value = data_get($params, $paramName);
                if (empty($value)) {
                    return $query;
                }
                $values = collect(is_iterable($value) ? $value : [$value])->toArray();
                $or = preg_match('/^or_/', $paramName) === 1;
                $exclude = preg_match('/^(or_)?exclude_/', $paramName) === 1;
                $methodName = Str::camel(
                    ($or ? 'or-' : '').'where-'.($exclude ? 'not-in' : 'in')
                );

                return $query->{$methodName}($column, $values);
            }, $query);
        },
            $query);

        if (isset($params['identifier'])) {
            $identifier = $params['identifier'];
            $query->where(function ($query) use ($identifier) {
                $this->getQueryFromIdentifier($query, $identifier);
            });
        }

        if (isset($params['order'])) {
            $order = $params['order'];
            if (is_array($order) && isset($order[0]) && is_array($order[0])) {
                foreach ($order as $subOrder) {
                    if (is_array($subOrder)) {
                        $this->orderQuery($query, $subOrder[0] ?? null, $subOrder[1] ?? null);
                    }
                }
            } elseif (is_array($order)) {
                $this->orderQuery($query, $order[0] ?? null, $order[1] ?? null);
            } else {
                $this->orderQuery($query, $order, $params['order_direction'] ?? null);
            }
        }

        return $query;
    }

    /**
     * Order the query by a column of the `order` param, unless the column is
     * not orderable (see isOrderableColumn()). A missing or invalid direction
     * falls back to ascending.
     */
    protected function orderQuery($query, $column, $direction = null)
    {
        if (is_string($column)) {
            $column = trim($column);
        }
        if (
            ! ($column instanceof Expression || (is_string($column) && $column !== '')) ||
            ! $this->isOrderableColumn($column)
        ) {
            return $query;
        }

        $direction = is_string($direction) ? strtolower(trim($direction)) : '';

        return $query->orderBy($column, in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc');
    }

    /**
     * Whether the `order` param can order by the column. Expressions come from
     * code and are always accepted. When getOrderableColumns() returns a list,
     * the column must match it; otherwise the column, or the JSON column a
     * path points into, must not be one of getUnorderableColumns().
     */
    protected function isOrderableColumn($column): bool
    {
        if ($column instanceof Expression) {
            return true;
        }

        $column = strtolower($column);
        $orderableColumns = $this->getOrderableColumns();
        if (! is_null($orderableColumns)) {
            return collect($orderableColumns)->contains(
                fn ($pattern) => Str::is(strtolower($pattern), $column)
            );
        }

        $name = trim(Str::afterLast(Str::before($column, '->'), '.'), ' `"[]');

        return ! in_array($name, array_map('strtolower', $this->getUnorderableColumns()), true);
    }

    /**
     * Columns that the `order` param accepts, where `*` matches any characters,
     * or null to accept every column but the unorderable ones.
     */
    protected function getOrderableColumns(): ?array
    {
        return $this->orderableColumns;
    }

    /**
     * Columns that the `order` param refuses when no orderable columns are set:
     * the model's hidden attributes (passwords, tokens, secrets), except its
     * timestamps, so that a request can't sort by values it can't read.
     */
    protected function getUnorderableColumns(): array
    {
        $model = $this->newModel();
        $timestamps = [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()];
        if (method_exists($model, 'getDeletedAtColumn')) {
            $timestamps[] = $model->getDeletedAtColumn();
        }

        return array_values(array_diff($model->getHidden(), $timestamps));
    }

    /**
     * Match a column against a value literally with LIKE: the comparison stays
     * case-insensitive, but `%` and `_` in the value are escaped instead of
     * acting as wildcards.
     */
    protected function whereLikeLiteral($query, string $column, string $value)
    {
        $escapedValue = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
        $column = $this->newModel()->getConnection()->getQueryGrammar()->wrap($column);

        return $query->whereRaw(
            $column.' '.$this->caseInsensitiveLikeOperator()." ? ESCAPE '!'",
            [$escapedValue],
        );
    }

    /**
     * LIKE is case-insensitive on SQLite and with MySQL's default collations,
     * but case-sensitive on PostgreSQL, which has ILIKE.
     */
    protected function caseInsensitiveLikeOperator(): string
    {
        return $this->newModel()->getConnection()->getQueryGrammar() instanceof PostgresGrammar ? 'ILIKE' : 'LIKE';
    }

    protected function getQueryFromIdentifier(
        $query,
        $identifier,
        $handleColumn = null,
        $idColumn = 'id'
    ) {
        $handleColumn = $handleColumn ?? $this->identifierHandleColumn;
        $identifiers = self::getIdsFromItems($identifier);
        $ids = collect($identifiers)
            ->filter(function ($value) {
                return is_numeric($value);
            })
            ->values()
            ->toArray();
        $handles = collect($identifiers)
            ->filter(function ($value) {
                return ! is_numeric($value);
            })
            ->values()
            ->toArray();
        if (count($ids) > 0) {
            $query->whereIn($idColumn, $ids);
        }
        if (count($handles) > 0) {
            $query->{count($ids) > 0 ? 'orWhereIn' : 'whereIn'}($handleColumn, $handles);
        }

        return $query;
    }

    protected function getJsonAttributeName()
    {
        return $this->jsonAttributes[0] ?? $this->jsonAttributeName;
    }

    /**
     * The JSON attributes that updates merge into.
     *
     * @return list<string>
     */
    protected function getJsonAttributes(): array
    {
        return $this->jsonAttributes ?? (is_string($this->jsonAttributeName) ? [$this->jsonAttributeName] : []);
    }

    /**
     * The JSON attributes, other than the first, that an update gives as a whole: their
     * keys are merged into the stored value (docs/json-data.md, "Repositories").
     *
     * @return list<string>
     */
    protected function getMergedJsonAttributes(): array
    {
        return array_slice($this->getJsonAttributes(), 1);
    }

    /**
     * Merges the keys an update gives for each of the other JSON attributes into its stored
     * value. The references that don't resolve are put back first, so they aren't lost.
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function mergeJsonAttributes(Model $model, array $data): void
    {
        foreach ($this->getMergedJsonAttributes() as $attribute) {
            if (! array_key_exists($attribute, $data)) {
                continue;
            }
            $update = $data[$attribute];
            if (! is_array($update)) {
                $model->setAttribute($attribute, $update);

                continue;
            }
            $current = JsonDataCast::getValueWithUnresolvedReferences($model, $attribute);
            $merged = array_replace(is_array($current) ? $current : [], $update);
            $model->setAttribute($attribute, count($merged) > 0 ? $merged : null);
        }
    }

    protected function getJsonAttributeFillable()
    {
        return $this->jsonAttributeFillable;
    }

    protected function getJsonAttributeExclude()
    {
        return $this->jsonAttributeExclude;
    }

    /**
     * The entity of one of the repository's models, as `to_entity()` makes it: the
     * model's `toEntity()`, which gives the repository's `TEntity`.
     *
     * @return TEntity|null
     */
    public function getEntityFromModel($model)
    {
        /** @var TEntity|null $entity */
        $entity = to_entity($model);

        return $entity;
    }

    public function getEntitiesFromModels($models)
    {
        return $models
            ->map(function ($model) {
                return $this->getEntityFromModel($model);
            })
            ->toBase();
    }

    public static function getIdFromItem($item)
    {
        return to_id($item);
    }

    public static function getIdsFromItems($items)
    {
        return collect(is_iterable($items) ? $items : [$items])
            ->map(function ($item) {
                return self::getIdFromItem($item);
            })
            ->filter(function ($item) {
                return ! empty($item);
            })
            ->toArray();
    }
}
