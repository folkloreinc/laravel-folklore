<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Block as BlockContract;

class Block implements BlockContract
{
    protected $model;

    protected $data;

    public function __construct($model)
    {
        $this->model = $model;
        $this->data = $model->data;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function type(): string
    {
        return $this->model->type;
    }

    public function data(): ?array
    {
        return $this->model->data;
    }
}
