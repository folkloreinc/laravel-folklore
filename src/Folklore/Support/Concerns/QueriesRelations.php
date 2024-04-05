<?php

namespace Folklore\Support\Concerns;

use Closure;
use Folklore\Repositories\Resources;
use Illuminate\Support\Str;

trait QueriesRelations
{
    protected function buildQueryFromBelongsToParams($query, $params, $belongsTo)
    {
        return collect($belongsTo)->reduce(function ($query, $key) use ($params) {
            return collect([
                $key,
                'or_' . $key,
                'exclude_' . $key,
                'or_exclude_' . $key,
                $key . '_id',
                'or_' . $key . '_id',
                'exclude_' . $key . '_id',
                'or_exclude_' . $key . '_id',
            ])->reduce(function ($query, $paramName) use ($key, $params) {
                $paramValue = data_get($params, $paramName);
                if (empty($paramValue)) {
                    return $query;
                }
                $ids = Resources::getIdsFromItems($paramValue);
                $or = preg_match('/^or_/', $paramName) === 1;
                $exclude = preg_match('/^(or_)?exclude_/', $paramName) === 1;
                $methodName = Str::camel(
                    ($or ? 'or-' : '') . 'where-' . ($exclude ? 'not-in' : 'in')
                );
                return $query->{$methodName}($key . '_id', $ids);
            }, $query);
        }, $query);
    }

    protected function buildQueryFromRelationsParams($query, $params, $relations)
    {
        return $query->where(function ($query) use ($params, $relations) {
            return collect($relations)->reduce(function ($query, $relation, $paramName) use (
                $params
            ) {
                if (is_numeric($paramName)) {
                    $paramName = $relation;
                }
                return collect([
                    $paramName,
                    'or_' . $paramName,
                    'exclude_' . $paramName,
                    'or_exclude_' . $paramName,
                ])->reduce(function ($query, $realParamName) use ($params, $relation) {
                    $paramValue = data_get($params, $realParamName);
                    if (empty($paramValue)) {
                        return $query;
                    }
                    $ids = Resources::getIdsFromItems($paramValue);
                    $or = preg_match('/^or_/', $realParamName) === 1;
                    $exclude = preg_match('/^(or_)?exclude_/', $realParamName) === 1;
                    $methodName = Str::camel(
                        ($or ? 'or-' : '') . 'where-' . ($exclude ? 'doesnt-have' : 'has')
                    );
                    $relationName = is_array($relation) ? $relation[0] : $relation;
                    $column = is_array($relation) ? $relation[1] : 'id';
                    return $query->{$methodName}($relationName, function ($query) use (
                        $relationName,
                        $ids,
                        $column
                    ) {
                        $table = $query->getModel()->getTable();
                        if ($column instanceof Closure) {
                            $column($query, $ids, $relationName);
                        } else {
                            $query->whereIn($table . '.' . $column, $ids);
                        }
                    });
                }, $query);
            },
            $query);
            return $query;
        });
    }
}
