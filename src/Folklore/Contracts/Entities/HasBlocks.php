<?php

namespace Folklore\Contracts\Entities;

use Illuminate\Support\Collection;

interface HasBlocks
{
    public function blocks(): Collection;
}
