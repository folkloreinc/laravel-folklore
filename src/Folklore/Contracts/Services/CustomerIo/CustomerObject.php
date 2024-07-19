<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Resource;
use Illuminate\Support\Collection;

interface CustomerObject extends Resource
{
    public function type(): string;

    public function data(): ?array;

    public function relationships(): ?Collection;
}
