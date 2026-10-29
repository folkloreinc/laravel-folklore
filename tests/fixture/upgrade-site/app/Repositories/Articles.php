<?php

namespace App\Repositories;

use App\Repositories\Concerns\WritesReferences;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Repositories\Entities;

class Articles extends Entities
{
    use WritesReferences;

    protected $jsonAttributeName = 'data';

    public function boot(): void
    {
        JsonDataCast::macro('medias:before_set', fn ($value) => $value);
    }

    protected function syncRelations($model, $data = [])
    {
        $model->medias()->sync($data['medias'] ?? []);
        $model->tags()->attach($data['tags'] ?? []);
    }

    protected function cover(int $id): string
    {
        return "media://{$id}";
    }
}
