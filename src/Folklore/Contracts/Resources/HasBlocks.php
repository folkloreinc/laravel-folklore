<?php

namespace Folklore\Contracts\Resources;

use Illuminate\Support\Collection;

interface HasBlocks
{
    public function blocks(): Collection;
}
