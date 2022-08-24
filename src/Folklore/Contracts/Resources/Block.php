<?php

namespace Folklore\Contracts\Resources;

use Panneau\Contracts\ResourceItem;

interface Block extends Resource, ResourceItem
{
    public function type(): string;

    public function data(): ?array;
}
