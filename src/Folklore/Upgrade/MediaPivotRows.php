<?php

namespace Folklore\Upgrade;

use Illuminate\Database\Eloquent\Model;

/**
 * The rows of the mediatheque's `medias_pivot` that 1.x wrote without a handle for the
 * media referenced in the JSON data of a model. 2.0 syncs them under the `json` handle,
 * and leaves the rows without a handle: `folklore:upgrade` syncs the media under the
 * handle, then removes them.
 */
final readonly class MediaPivotRows
{
    /**
     * @param  class-string<Model>  $model
     * @param  string  $table  The pivot table, with its prefix.
     * @param  string  $morphType  The column of the morph type.
     * @param  string  $morphClass  The model's morph type.
     * @param  int  $count  The rows without a handle to remove.
     * @param  int  $models  The model's rows that have some.
     */
    public function __construct(
        public string $model,
        public string $table,
        public string $morphType,
        public string $morphClass,
        public int $count,
        public int $models = 0,
    ) {}
}
