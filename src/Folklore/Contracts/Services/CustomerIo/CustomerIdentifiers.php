<?php

namespace Folklore\Contracts\Services\CustomerIo;

interface CustomerIdentifiers
{
    public function cioId(): string;

    public function email(): ?string;

    public function id(): ?string;
}
