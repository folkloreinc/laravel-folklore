<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Block as BlockContract;
use Folklore\Contracts\Resources\HasModel;
use Illuminate\Database\Eloquent\Model;

class Block implements BlockContract, HasModel
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

    public function getModel(): Model
    {
        return $this->model;
    }
}
