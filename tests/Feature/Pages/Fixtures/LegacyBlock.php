<?php

namespace Folklore\Tests\Feature\Pages\Fixtures;

use Folklore\Models\Block;

/**
 * A 1.1 site's block that adds to the package's 1.x declarations.
 */
class LegacyBlock extends Block
{
    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return parent::getJsonDataRelations($key, $value, $attributes);
    }
}
