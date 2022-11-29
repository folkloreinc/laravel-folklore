<?php

namespace Folklore\Support\Concerns;

use Folklore\Contracts\Resources\HasModel;
use Folklore\Contracts\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

trait SyncRelations
{
    protected function saveItemsToRelation($relation, $items)
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
                return $related->newInstance($item);
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
        $relation->saveMany($models);
    }
}
