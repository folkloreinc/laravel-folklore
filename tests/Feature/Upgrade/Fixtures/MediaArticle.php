<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures;

use Folklore\JsonData\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Illuminate\Database\Eloquent\Model;

/**
 * A model on `JsonDataCast` with laravel-mediatheque 2's `HasMedias`: 2.0 syncs the media
 * of its JSON data under the `json` handle, where 1.x synced them without a handle.
 */
class MediaArticle extends Model
{
    use HasMedias;

    protected $table = 'upgrade_media_articles';

    public $timestamps = false;

    protected $casts = [
        'data' => JsonDataCast::class,
    ];
}
