<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\HasEntityKey;
use Folklore\Contracts\Entities\HasModel;
use Illuminate\Database\Eloquent\Model;

/**
 * A typed base for new entities: an immutable object that reads a model. Its id is the
 * model's entity key, the same value its repository finds by (docs/decisions/0015).
 *
 * The 1.x entities don't extend it, so their protected properties keep working.
 *
 * @template TModel of Model
 *
 * @implements HasModel<TModel>
 */
abstract class ModelEntity implements Entity, HasModel
{
    /**
     * @param  TModel  $model
     */
    public function __construct(protected readonly Model $model) {}

    /**
     * The model's entity key (`HasEntityKey`), or its key when it doesn't implement the
     * contract. An empty string when the model has none yet.
     */
    public function id(): string
    {
        $id = $this->model instanceof HasEntityKey ? $this->model->getEntityKey() : $this->model->getKey();

        return is_scalar($id) ? (string) $id : '';
    }

    /**
     * @return TModel
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
