<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Entities\Entity;

interface Campaign extends Entity
{
    public function name(): string;
}
