<?php

namespace Folklore\Models;

use Illuminate\Database\Eloquent\Model;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Contracts\Resources\Block as BlockContract;
use Folklore\Contracts\Resources\Resourcable;
use Folklore\Resources\Block as BlockResource;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Support\Concerns\HasTypedResource;

class Block extends Model implements Resourcable, HasJsonDataRelations
{
    use HasMedias, HasTypedResource;

    protected $table = 'blocks';

    protected $fillable = ['handle', 'type', 'data'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    protected $typedResources = [];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [
            'image' => 'medias',
            'blocks.*' => 'blocks',
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
        return $this->toTypedResource() ?? new BlockResource($this);
    }
}
