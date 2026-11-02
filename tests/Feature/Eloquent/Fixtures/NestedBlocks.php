<?php

namespace Folklore\Tests\Feature\Eloquent\Fixtures;

use Folklore\Repositories\Entities;
use Illuminate\Database\Eloquent\Model;

class NestedBlocks extends Entities
{
    protected function newModel(): Model
    {
        return new NestedBlock;
    }
}
