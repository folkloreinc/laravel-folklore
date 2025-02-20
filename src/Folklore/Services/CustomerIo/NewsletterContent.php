<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;

class NewsletterContent extends MessageContent implements NewsletterContentContract
{
    public function id(): string
    {
        return data_get($this->data, 'id');
    }
}
