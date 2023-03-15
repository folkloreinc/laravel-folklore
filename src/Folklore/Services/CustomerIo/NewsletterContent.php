<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;

class NewsletterContent implements NewsletterContentContract
{
    protected $data;

    protected $content;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function id(): string
    {
        return data_get($this->data, 'id');
    }

    public function name(): string
    {
        return data_get($this->data, 'name');
    }

    public function body(): string
    {
        return data_get($this->data, 'body');
    }
}
