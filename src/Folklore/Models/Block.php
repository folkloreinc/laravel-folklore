<?php

namespace Folklore\Models;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Entities\Block as BlockContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Entities\Block as BlockEntity;
use Folklore\Mediatheque\Support\Traits\HasMedias;
use Folklore\Support\Concerns\HasTypedEntity;
use Illuminate\Database\Eloquent\Model;

class Block extends Model implements HasJsonDataRelations, ToEntity
{
    use HasMedias, HasTypedEntity;

    protected $table = 'blocks';

    protected $fillable = ['handle', 'type', 'data'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    protected $entitiesByType = [];

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

    public function toEntity(): BlockContract
    {
        return $this->toTypedEntity() ?? new BlockEntity($this);
    }
}
