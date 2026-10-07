<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class BlocksResource extends Resource
{
    public static $types = [TextBlock::class, ColumnsBlock::class];

    public function fields(): array
    {
        return [Text::make('title')];
    }
}
