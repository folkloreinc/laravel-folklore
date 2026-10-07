<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Panneau\Fields\Text;
use Panneau\Support\ResourceType;

class TextBlock extends ResourceType
{
    public function fields(): array
    {
        return [Text::make('body')];
    }
}
