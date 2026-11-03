<?php

namespace Folklore\Tests\Feature\Pages\Fixtures;

use Folklore\Entities\Block;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;

/**
 * A block that holds blocks.
 */
class StackBlock extends Block
{
    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            'title' => Json::string()->translatable(),
            'blocks' => $level->blocks(),
        ])->additional(false);
    }
}
