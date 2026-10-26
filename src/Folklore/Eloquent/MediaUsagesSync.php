<?php

namespace Folklore\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Syncs the media referenced in the JSON columns of a model into laravel-mediatheque 2's
 * `medias_pivot`, which is also its usage graph, under their own handle, so that the media
 * attached otherwise (`attachMedia()`) stay (laravel-mediatheque's decisions/0020). It is
 * set with `JsonData::syncUsing()` when the mediatheque is installed (docs/json-data.md,
 * "Syncing").
 */
final class MediaUsagesSync
{
    /**
     * The handle of the pivot under which the media of JSON data are synced.
     */
    public const string HANDLE = 'json';

    /**
     * @param  Relation<Model, Model, mixed>  $relation
     * @param  list<mixed>  $ids
     */
    public function __invoke(Model $model, string $relationName, Relation $relation, array $ids): bool
    {
        if (! $this->syncs($model, $relationName) || ! method_exists($model, 'syncMedias')) {
            return false;
        }

        // Media keys are integers; a reference that isn't one can't be a media
        $mediaIds = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $mediaIds[] = (int) $id;
            }
        }
        $model->syncMedias([self::HANDLE => $mediaIds]);
        $model->unsetRelation('medias');

        return true;
    }

    /**
     * The keys of the media synced under the handle, for laravel-json-data to plan a sync
     * without writing (`json-data:sync-references --dry-run`), or null for a relation
     * that this callback doesn't sync. Set as the second callback of
     * `JsonData::syncUsing()`.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @return list<int>|null
     */
    public function synced(Model $model, string $relationName, Relation $relation): ?array
    {
        if (! $this->syncs($model, $relationName) || ! $relation instanceof BelongsToMany) {
            return null;
        }

        $keys = [];
        foreach ((clone $relation)->wherePivot('handle', self::HANDLE)->pluck($relation->getQualifiedRelatedPivotKeyName()) as $key) {
            if (is_int($key) || (is_string($key) && ctype_digit($key))) {
                $keys[] = (int) $key;
            }
        }

        return $keys;
    }

    /**
     * Whether the callback syncs the relation: the media of a model that uses
     * laravel-mediatheque 2's `HasMedias`.
     */
    private function syncs(Model $model, string $relationName): bool
    {
        return $relationName === 'medias'
            && in_array('Folklore\\Mediatheque\\Concerns\\HasMedias', class_uses_recursive($model), true);
    }
}
