<?php

namespace Folklore\Tests\Feature\Eloquent\Fixtures;

use Folklore\JsonData\Contracts\HasJsonDataSchema;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;

/**
 * A game that declares its `data` column with a schema, instead of the 1.x declarations
 * of Game, which still apply to the attributes without one.
 */
class SchemaGame extends Game implements HasJsonDataSchema
{
    public function jsonDataSchema(string $attribute): ?ObjectType
    {
        return $attribute === 'data' ? Json::object([
            'title' => Json::string()->translatable()->extract('title_{locale}'),
            'intro' => Json::string()->input('subtitle'),
            'image' => Json::media()->translatable(),
            'cover' => Json::media(),
        ])->additional(false) : null;
    }

    public function getMorphClass()
    {
        return Game::class;
    }
}
