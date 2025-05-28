<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Entities\Entity;
use Illuminate\Support\Collection;

interface CustomerObject extends Entity
{
    public function type(): string;

    public function attributes(): ?array;

    public function relationships(): ?Collection;
}
