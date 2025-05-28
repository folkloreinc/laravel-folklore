<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Entities\Entity;

interface TransactionalMessage extends Entity
{
    public function name(): string;
}
