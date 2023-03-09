<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\CustomerIdentifiers as CustomerIdentifiersContract;

class CustomerIdentifiers implements CustomerIdentifiersContract
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function cioId(): string
    {
        return data_get($this->data, 'cio_id');
    }

    public function email(): ?string
    {
        return data_get($this->data, 'email');
    }

    public function id(): ?string
    {
        return data_get($this->data, 'id');
    }
}
