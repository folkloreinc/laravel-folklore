<?php

namespace Folklore\Support\Concerns;

use Folklore\Contracts\Resources\HasModel;
use Folklore\Contracts\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

trait SyncRelations
{
    protected function saveItemsToRelation(HasOneOrMany $relation, $items, $key = 'id')
    {
        $related = $relation->getRelated();
        $items = collect($items)->map(function ($item) use ($related, $key) {
            if ($item instanceof HasModel) {
                return $item->getModel();
            }
            if ($item instanceof Resource) {
                return $item->id();
            }
            if (is_array($item)) {
                $keyName = $related->getKeyName();
                if ($keyName === $key || isset($item[$keyName])) {
                    return $related->newInstance($item, isset($item[$keyName]));
                }
                $existing = isset($item[$key])
                    ? $related
                        ->newQuery()
                        ->where($key, $item[$key])
                        ->first()
                    : null;
                return isset($existing) ? $existing->fill($item) : $related->newInstance($item);
            }
            return $item;
        });
        $keys = $items
            ->filter(function ($model) {
                return is_string($model);
            })
            ->values();
        $models = $items
            ->filter(function ($item) {
                return $item instanceof Model;
            })
            ->values()
            ->merge(
                !$keys->isEmpty()
                    ? $related
                        ->newQuery()
                        ->whereIn($key, $keys->toArray())
                        ->get()
                    : []
            );
        return $relation->saveMany($models);
    }

    protected function syncItemsToRelation(HasOneOrMany $relation, $items, $key = 'id')
    {
        $models = $this->saveItemsToRelation($relation, $items, $key);
        $keys = collect($models)->map(function ($model) use ($key) {
            return $model->{$key};
        });
        $relation->whereNotIn($key, $keys)->delete();
        return $models;
    }
}
