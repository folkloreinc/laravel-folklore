<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Illuminate\Database\Eloquent\Model;

/**
 * A 1.1 model on `JsonDataCast` whose `gallery` syncs its `medias` relation, with
 * laravel-mediatheque 2's `HasMedias`: 2.0 syncs these media under the `json` handle,
 * where 1.x synced them without a handle.
 */
class MediaArticle extends Model implements HasJsonDataRelations
{
    use HasMedias;

    protected $table = 'upgrade_media_articles';

    public $timestamps = false;

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return ['gallery.*' => 'medias'];
    }
}
