<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures\Schemas;

use Folklore\Repositories\Entities;
use Illuminate\Database\Eloquent\Model;

/**
 * A repository without `$jsonAttributeFillable`: once its model has a schema, it fills it
 * with `JsonData::fill()`.
 */
class PlainGames extends Entities
{
    protected function newModel(): Model
    {
        return new LegacyGame;
    }
}
