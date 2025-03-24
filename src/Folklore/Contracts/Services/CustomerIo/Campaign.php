<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Resource;

interface Campaign extends Resource
{
    public function name(): string;
}
