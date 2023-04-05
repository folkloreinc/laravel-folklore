<?php

namespace Folklore\Support\Concerns;

use Folklore\Contracts\Resources\Resource;
use Illuminate\Support\Collection;

trait QueriesRelations
{
    protected function buildQueryFromBelongsToParams($query, $params, $belongsTo)
    {
        return collect($belongsTo)->reduce(function ($newQuery, $key) use ($params) {
            if (isset($params[$key]) || isset($params[$key . '_id'])) {
                $item = data_get($params, $key, data_get($params, $key . '_id'));
                $ids = collect(is_array($item) || $item instanceof Collection ? $item : [$item])
                    ->map(function ($item) {
                        return $item instanceof Resource ? $item->id() : $item;
                    })
                    ->toArray();
                if (sizeof($ids)) {
                    $newQuery->whereIn($key . '_id', $ids);
                }
            }
            if (isset($params['exclude_' . $key]) || isset($params['exclude_' . $key . '_id'])) {
                $item = data_get(
                    $params,
                    'exclude_' . $key,
                    data_get($params, 'exclude_' . $key . '_id')
                );
                $ids = collect(is_array($item) || $item instanceof Collection ? $item : [$item])
                    ->map(function ($item) {
                        return $item instanceof Resource ? $item->id() : $item;
                    })
                    ->toArray();
                if (sizeof($ids)) {
                    $newQuery->whereNotIn($key . '_id', $ids);
                }
            }
            return $newQuery;
        }, $query);
    }
}
