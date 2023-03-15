<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Resource;

interface Newsletter extends Resource
{
    public function name(): string;

    public function type(): string;

    public function medium(): string;

    public function content(): NewsletterContent;
}
