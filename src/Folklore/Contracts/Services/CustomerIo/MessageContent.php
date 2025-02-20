<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Resource;

interface MessageContent
{
    public function type(): string;

    public function name(): ?string;

    public function subject(): ?string;

    public function body(): ?string;
}
