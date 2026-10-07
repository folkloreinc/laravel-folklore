<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class PagesResource extends Resource
{
    public function fields(): array
    {
        return [Text::make('title')];
    }
}
