<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Resource;

interface NewsletterContent extends Resource
{
    public function name(): string;

    public function body(): string;
}
