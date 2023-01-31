<?php

namespace Folklore\Support\Concerns;

use Folklore\Contracts\Resources\HasModel;
use Folklore\Contracts\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

trait SyncRelations
{
    protected function saveItemsToRelation(HasOneOrMany $relation, $items)
    {
        $related = $relation->getRelated();
        $items = collect($items)->map(function ($item) use ($related) {
            if ($item instanceof HasModel) {
                return $item->getModel();
            }
            if ($item instanceof Resource) {
                return $item->id();
            }
            if (is_array($item)) {
                return $related->newInstance($item, isset($item['id']));
            }
            return $item;
        });
        $ids = $items
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
                !$ids->isEmpty()
                    ? $related
                        ->newQuery()
                        ->whereIn('id', $ids->toArray())
                        ->get()
                    : []
            );
        return $relation->saveMany($models);
    }

    protected function syncItemsToRelation(HasOneOrMany $relation, $items)
    {
        $models = $this->saveItemsToRelation($relation, $items);
        $ids = collect($models)->map(function ($model) {
            return $model->id;
        });
        $relation->whereNotIn('id', $ids)->delete();
        return $models;
    }
}
