<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A 1.1 model whose own code writes the references of its plain JSON column.
 */
class LegacyArticle extends Model
{
    protected $table = 'upgrade_legacy_articles';

    public $timestamps = false;

    protected $casts = [
        'data' => 'json',
    ];
}
