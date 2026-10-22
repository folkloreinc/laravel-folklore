<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\HasModel;
use Illuminate\Database\Eloquent\Model;

/**
 * A typed base for new entities: an immutable object that reads a model. Its id is the
 * value of `$idColumn`, the same column its repository finds by (docs/decisions/0015).
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
     * The model's column that holds the public id. Set it to the repository's `$idColumn`.
     */
    protected string $idColumn = 'id';

    /**
     * @param  TModel  $model
     */
    public function __construct(protected readonly Model $model) {}

    /**
     * The value of `$idColumn`, or an empty string when the model has none yet.
     */
    public function id(): string
    {
        $id = $this->model->getAttribute($this->idColumn);

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
