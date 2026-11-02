<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures\Schemas;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Illuminate\Database\Eloquent\Model;

/**
 * A 1.1 contest whose declarations declare nothing.
 */
class EmptyContest extends Model implements HasJsonDataRelations
{
    protected $table = 'contests';

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [];
    }
}
