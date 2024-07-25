<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\CustomerObject as CustomerObjectContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CustomerObject implements CustomerObjectContract
{
    public function __construct(protected array $data, protected ?Collection $relationships = null)
    {
    }

    public function id(): string
    {
        return data_get($this->data, 'identifiers.id', $this->data['id']);
    }

    public function type(): string
    {
        return data_get($this->data, 'object_type_id', $this->data['type']);
    }

    public function attributes(): ?array
    {
        return data_get($this->data, 'attributes', Arr::except($this->data, ['id', 'type']));
    }

    public function relationships(): ?Collection
    {
        return $this->relationships;
    }
}
