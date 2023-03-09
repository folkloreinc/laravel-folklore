<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Resource;

interface TransactionalMessage extends Resource
{
    public function name(): string;
}
