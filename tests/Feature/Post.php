<?php

namespace Folklore\Tests\Feature;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements HasJsonDataRelations
{
    use HasMedias;

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [
            'image' => 'medias',
            'images.*' => 'medias',
            'image_hybrid' => [
                'relation' => 'medias',
                'set' => function ($item, $path, $relationName) {
                    return [
                        'media' => $item->id,
                    ];
                },
            ],
        ];
    }
}
