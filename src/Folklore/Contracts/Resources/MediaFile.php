<?php

namespace Folklore\Contracts\Resources;

use Panneau\Contracts\ResourceItem;

interface MediaFile extends ResourceItem, Resource
{
    public function id(): string;

    public function url(): string;
}
