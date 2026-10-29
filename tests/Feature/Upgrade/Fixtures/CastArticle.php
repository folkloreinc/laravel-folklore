<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures;

use Folklore\Eloquent\JsonDataCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A model on `JsonDataCast`, whose `tag://` references fall back to its `tag()` relation.
 */
class CastArticle extends Model
{
    protected $table = 'upgrade_cast_articles';

    public $timestamps = false;

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    /**
     * @return BelongsTo<LegacyArticle, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(LegacyArticle::class);
    }
}
