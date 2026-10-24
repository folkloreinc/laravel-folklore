<?php

namespace Folklore\Tests\Feature\Eloquent\Fixtures;

use Folklore\Repositories\Entities;
use Illuminate\Database\Eloquent\Model;

/**
 * A repository of games with a schema. `$jsonAttributeFillable` doesn't apply to them:
 * `JsonData::fill()` reads the input.
 */
class SchemaGames extends Entities
{
    protected $jsonAttributeFillable = '*';

    protected function newModel(): Model
    {
        return new SchemaGame;
    }
}
