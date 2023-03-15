<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\TransactionalMessage as TransactionalMessageContract;

class TransactionalMessage implements TransactionalMessageContract
{
    protected $data;

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
}
