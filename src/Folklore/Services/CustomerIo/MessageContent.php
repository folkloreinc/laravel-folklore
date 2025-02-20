<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\MessageContent as MessageContentContract;

class MessageContent implements MessageContentContract
{
    protected $data;

    protected $content;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function type(): string
    {
        return data_get($this->data, 'type');
    }

    public function name(): ?string
    {
        return data_get($this->data, 'name');
    }

    public function subject(): ?string
    {
        return data_get($this->data, 'subject');
    }

    public function body(): ?string
    {
        return data_get($this->data, 'body');
    }
}
