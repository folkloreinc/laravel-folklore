<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\HasModel;
use Illuminate\Database\Eloquent\Model;

/**
 * A typed base for new entities: an immutable object that reads a model. Its id is the
 * model's route key, the public id.
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

    public function id(): string
    {
        $key = $this->model->getRouteKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * @return TModel
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
