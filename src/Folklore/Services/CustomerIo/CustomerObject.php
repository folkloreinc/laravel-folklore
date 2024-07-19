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
        return $this->data['id'];
    }

    public function type(): string
    {
        return $this->data['type'];
    }

    public function data(): ?array
    {
        return Arr::except($this->data, ['id', 'type']);
    }

    public function relationships(): ?Collection
    {
        return $this->relationships;
    }
}
