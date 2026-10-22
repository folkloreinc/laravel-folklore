<?php

namespace Folklore\Contracts\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * An entity backed by a model. The model is for the edges that need one, such as
 * Fortify or Sanctum, not for business code.
 *
 * @template TModel of Model = Model
 */
interface HasModel
{
    /**
     * @return TModel
     */
    public function getModel(): Model;
}
