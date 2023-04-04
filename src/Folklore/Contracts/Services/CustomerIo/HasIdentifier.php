<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Illuminate\Support\Collection;

interface HasIdentifier
{
    public function customerIoIdentifier(): ?string;
}
