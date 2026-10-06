<?php

namespace Folklore\Contracts\Services\CustomerIo;

interface HasIdentifier
{
    public function customerIoIdentifier(): ?string;
}
