<?php

namespace Folklore\Repositories;

use Folklore\Contracts\Repositories\Resources as ResourcesContract;
use Folklore\Contracts\Resources\Resource;
use Folklore\Contracts\Resources\Resourcable;
use Illuminate\Database\Eloquent\Model;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Support\OffsetPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Laravel\Scout\Builder as ScoutBuilder;
use Illuminate\Support\Str;

abstract class Resources implements ResourcesContract
{
    protected $globalQuery = [];

    protected $jsonAttributeName = 'data';

    protected $jsonAttributeFillable = null;

    protected $jsonAttributeExclude = null;

    protected $queryColumns = [];

    protected $identifierHandleColumn = 'handle';

    abstract protected function newModel(): Model;

    protected function newQuery()
    {
        return $this->newModel()->newQuery();
    }

    public function findById(string $id): ?Resource
    {
        $model = $this->findModelById($id);
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function get(array $params = [], ?int $page = null, ?int $count = null)
    {
        $query = $this->newQueryWithParams($params);
        return $this->getFromQuery($query, $page, $count, $params);
    }

    public function count(array $params = []): int
    {
        $query = $this->buildQueryFromParams($this->newQuery(), $this->getQueryParams($params));
        return $query->count();
    }

    public function has(array $params = []): bool
    {
        $query = $this->buildQueryFromParams($this->newQuery(), $this->getQueryParams($params));
        return $query->exists();
    }

    protected function getFromQuery(
        $query,
        ?int $page = null,
        ?int $count = null,
        array $params = []
    ) {
        if (
            !is_null($page) &&
            isset($params['offset_paginator']) &&
            $params['offset_paginator'] === true
        ) {
            $query->skip($page);
            if (!is_null($count)) {
                $query->take($count);
            }
            $models = new OffsetPaginator(
                $query->get(),
                $query->toBase()->getCountForPagination(),
                $page
            );
        } elseif (!is_null($page)) {
            $models =
                $query instanceof ScoutBuilder
                    ? $query->paginate($count, 'page', $page)
                    : $query->paginate($count, ['*'], 'page', $page);
        } else {
            if (!is_null($count)) {
                $query->take($count);
            }
            $models = $query->get();
        }

        $collection = $models->map(function ($model) {
            return $model instanceof Resourcable ? $model->toResource() : $model;
        });

        if ($models instanceof AbstractPaginator) {
            $models->setCollection($collection);
            return $models;
        }
        return $collection;
    }

    public function create($data): Resource
    {
        $model = $this->newModel();
        $this->saveData($model, $data);
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function update(string $id, $data): ?Resource
    {
        $model = $this->findModelById($id);
        if (is_null($model)) {
            return null;
        }
        $this->saveData($model, $data);
        return $model instanceof Resourcable ? $model->toResource() : $model;
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

    protected function findModelById($id, $params = null)
    {
        return $this->newQuery($params)
            ->where('id', $id)
            ->first();
    }

    protected function saveData($model, $data)
    {
        $this->fillModel($model, $data);
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
        $jsonAttributeFillable = $this->getJsonAttributeFillable();
        if (is_null($jsonAttributeFillable)) {
            return;
        }

        $jsonAttributeName = $this->getJsonAttributeName();
        $jsonAttributeExclude = $this->getJsonAttributeExclude() ?? [];
        $currentAttributeValue = $model->{$jsonAttributeName};
        $fillable = $model->getFillable();
        $newAttributeValue = collect(
            $jsonAttributeFillable === '*'
                ? array_diff(array_keys($data), $fillable, $jsonAttributeExclude)
                : $jsonAttributeFillable
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
        }, $currentAttributeValue);
        $model->{$jsonAttributeName} = $newAttributeValue;
    }

    protected function syncRelations($model, $data)
    {
        if ($model instanceof HasJsonDataRelations) {
            $ids = JsonDataCast::syncRelations($model);
            if (!is_null($ids) && count($ids) > 0) {
                $model->refresh();
            }
        }
    }

    protected function newQueryWithParams($params = [])
    {
        return $this->buildQueryFromParams($this->newQuery(), $this->getQueryParams($params));
    }

    protected function getDefaultParams(): array
    {
        return [];
    }

    protected function getQueryParams(array $params): array
    {
        return array_merge($this->getDefaultParams(), $this->globalQuery, $params);
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
                'or_' . $param,
                'exclude_' . $param,
                'or_exclude_' . $param,
            ])->reduce(function ($query, $paramName) use ($params, $column) {
                $value = data_get($params, $paramName);
                if (empty($value)) {
                    return $query;
                }
                $values = collect(is_iterable($value) ? $value : [$value])->toArray();
                $or = preg_match('/^or_/', $paramName) === 1;
                $exclude = preg_match('/^(or_)?exclude_/', $paramName) === 1;
                $methodName = Str::camel(
                    ($or ? 'or-' : '') . 'where-' . ($exclude ? 'not-in' : 'in')
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
            if (is_array($params['order'])) {
                $order = $params['order'];
                if (isset($order[0]) && !empty($order[0]) && is_string($order[0])) {
                    if (isset($order[1]) && !empty($order[1])) {
                        $query->orderBy($order[0], $order[1]);
                    } else {
                        $query->orderBy($order[0], 'ASC');
                    }
                } elseif (isset($order[0]) && !empty($order[0]) && is_array($order[0])) {
                    foreach ($order as $subOrder) {
                        $query->orderBy($subOrder[0], $subOrder[1]);
                    }
                }
            } elseif (isset($params['order_direction']) && !empty($params['order_direction'])) {
                $query->orderBy($params['order'], $params['order_direction']);
            } else {
                $query->orderBy($params['order'], 'ASC');
            }
        }

        return $query;
    }

    protected function getQueryFromIdentifier(
        $query,
        $identifier,
        $handleColumn = null,
        $idColumn = 'id'
    ): ?string {
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
                return !is_numeric($value);
            })
            ->values()
            ->toArray();
        if (sizeof($ids) > 0) {
            $query->whereIn($idColumn, $ids);
        }
        if (sizeof($handles) > 0) {
            $query->{sizeof($ids) > 0 ? 'orWhereIn' : 'whereIn'}($handleColumn, $handles);
        }
        return $query;
    }

    protected function getJsonAttributeName()
    {
        return $this->jsonAttributeName;
    }

    protected function getJsonAttributeFillable()
    {
        return $this->jsonAttributeFillable;
    }

    protected function getJsonAttributeExclude()
    {
        return $this->jsonAttributeExclude;
    }

    public static function getIdFromItem($item)
    {
        if (is_numeric($item) || is_string($item)) {
            return $item;
        } elseif (is_array($item) && isset($item['id'])) {
            return $item['id'];
        } elseif ($item instanceof Model) {
            return $item->getKey();
        } elseif ($item instanceof Resource) {
            return $item->id();
        }
        return null;
    }

    public static function getIdsFromItems($items)
    {
        return collect(is_iterable($items) ? $items : [$items])
            ->map(function ($item) {
                return self::getIdFromItem($item);
            })
            ->filter(function ($item) {
                return !empty($item);
            })
            ->toArray();
    }
}
