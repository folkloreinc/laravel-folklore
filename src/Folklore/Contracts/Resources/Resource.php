<?php

namespace Folklore\Contracts\Resources;

use Panneau\Contracts\ResourceItem;

interface Resource extends ResourceItem
{
    public function id(): string;
}
