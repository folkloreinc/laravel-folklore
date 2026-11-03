<?php

namespace Folklore\Contracts\Pages;

use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;

/**
 * The entity class of a block type, in `site.pages.blocks.types`, gives the schema of its
 * blocks' data (docs/decisions/0023): their own properties, without `type`, `id` and
 * `handle`, which every type has. A type that holds blocks takes them from the level.
 */
interface DeclaresBlockSchema
{
    public static function blockSchema(BlockLevel $level): ObjectType;
}
