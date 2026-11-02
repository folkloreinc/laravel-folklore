<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures\Schemas;

use Folklore\Repositories\Entities;
use Illuminate\Database\Eloquent\Model;

/**
 * The repository of a 1.1 site: every key outside the fillable columns goes to the JSON
 * data. `$model` is the class it makes, so that a test gives it the converted model.
 */
class LegacyGames extends Entities
{
    protected $jsonAttributeFillable = '*';

    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(protected string $model = LegacyGame::class) {}

    protected function newModel(): Model
    {
        return new $this->model;
    }
}
