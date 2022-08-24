<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Contracts\Resources\Block as BlockContract;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Resources\Block as BlockResource;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;

class Block extends Model implements Resourcable, HasJsonDataRelations
{
    use HasMedias;

    protected $table = 'blocks';

    protected $fillable = ['handle', 'type', 'data'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [
            'medias' => ['image'],
            'blocks' => ['blocks.*'],
        ];
    }

    public function pages()
    {
        return $this->morphedByMany(Page::class, 'blockable', 'blocks_pivot');
    }

    public function blocks()
    {
        return $this->morphToMany(Block::class, 'blockable', 'blocks_pivot');
    }

    public function toResource(): BlockContract
    {
        return new BlockResource($this);
    }
}
