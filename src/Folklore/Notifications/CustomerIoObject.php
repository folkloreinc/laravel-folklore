<?php

namespace Folklore\Notifications;

use Folklore\Contracts\Services\CustomerIo\CustomerObject as CustomerObjectContract;
use Folklore\Services\CustomerIo\CustomerObject;
use Illuminate\Support\Collection;

class CustomerIoObject
{
    public $data = [];

    public ?Collection $relationships;

    public function __construct(public string $type, public string $id)
    {
    }

    public function data(array $data)
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function relationships($relationships)
    {
        $this->relationships = collect($relationships);
        return $this;
    }

    public function toObject(): CustomerObjectContract
    {
        return new CustomerObject(
            array_merge(
                [
                    'id' => $this->id,
                    'type' => $this->type,
                ],
                $this->data
            ),
            $this->relationships
        );
    }
}
