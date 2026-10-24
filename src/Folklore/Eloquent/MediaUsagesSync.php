<?php

namespace Folklore\Eloquent;

use Illuminate\Database\Eloquent\Model;
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
        if (
            $relationName !== 'medias'
            || ! in_array('Folklore\\Mediatheque\\Concerns\\HasMedias', class_uses_recursive($model), true)
            || ! method_exists($model, 'syncMedias')
        ) {
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
}
