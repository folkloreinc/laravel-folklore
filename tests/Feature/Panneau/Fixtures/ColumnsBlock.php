<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Folklore\Panneau\Fields\Blocks;
use Panneau\Support\ResourceType;

class ColumnsBlock extends ResourceType
{
    public function fields(): array
    {
        return [Blocks::make('blocks')->maxDepth(2)];
    }
}
