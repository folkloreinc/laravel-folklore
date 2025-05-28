<?php

namespace Folklore\Contracts\Services\CustomerIo;

interface MessageContent
{
    public function type(): ?string;

    public function name(): ?string;

    public function subject(): ?string;

    public function body(): ?string;
}
