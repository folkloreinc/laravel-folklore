<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures;

use Folklore\JsonData\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Illuminate\Database\Eloquent\Model;

/**
 * A model on `JsonDataCast` with `HasMedias` whose JSON data declares no `medias`
 * relation: its media are attached by other code, and stay.
 */
class PlainMediaArticle extends Model
{
    use HasMedias;

    protected $table = 'upgrade_media_articles';

    public $timestamps = false;

    protected $casts = [
        'data' => JsonDataCast::class,
    ];
}
