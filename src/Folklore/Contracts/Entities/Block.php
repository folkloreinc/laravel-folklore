<?php

namespace Folklore\Contracts\Entities;

use Panneau\Contracts\ResourceItem;

interface Block extends Entity, ResourceItem
{
    public function type(): string;

    public function data(): ?array;
}
