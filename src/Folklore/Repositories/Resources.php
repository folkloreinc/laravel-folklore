<?php

namespace Folklore\Repositories;

use Folklore\Contracts\Repositories\Resources as ResourcesContract;
use Folklore\Contracts\Resources\Resource;
use Folklore\Contracts\Resources\Resourcable;
use Illuminate\Database\Eloquent\Model;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Laravel\Scout\Builder as ScoutBuilder;

abstract class Resources implements ResourcesContract
{
    protected $globalQuery = [];

    protected $jsonAttributeName = 'data';

    protected $jsonAttributeFillable = null;

    protected $jsonAttributeExclude = null;

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
        return $this->getFromQuery($query, $page, $count);
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

    protected function getFromQuery($query, ?int $page = null, ?int $count = null)
    {
        if (!is_null($page)) {
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
        if (is_null($page)) {
            return $collection;
        }
        $models->setCollection($collection);
        return $models;
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
                $newValue = array_merge($newValue ?? [], $data[$field]);
            } elseif (isset($fieldValue)) {
                data_set($newValue, $path, $data[$field]);
            }
            return $newValue;
        }, $currentAttributeValue);
        $model->{$jsonAttributeName} = $newAttributeValue;
    }

    protected function syncRelations($model, $data)
    {
        if ($model instanceof HasJsonDataRelations) {
            JsonDataCast::syncRelations($model);
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
        if (isset($params['order'])) {
            if (is_array($params['order'])) {
                $query->orderBy($params['order'][0], $params['order'][1]);
            } else {
                $query->orderBy($params['order'], 'ASC');
            }
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
}
