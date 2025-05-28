<?php

namespace Folklore\Contracts\Entities;

use Panneau\Contracts\ResourceItem;

interface Entity extends ResourceItem
{
    public function id(): string;
}
