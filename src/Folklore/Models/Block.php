<?php

namespace Folklore\Models;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Entities\Block as BlockContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Entities\Block as BlockEntity;
use Folklore\JsonData\Contracts\HasJsonDataSchema;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Mediatheque\Concerns\HasMedias;
use Folklore\Models\Concerns\KeepsSubclassJsonDataRelations;
use Folklore\Support\Concerns\HasTypedEntity;
use Illuminate\Database\Eloquent\Model;

class Block extends Model implements HasJsonDataRelations, HasJsonDataSchema, ToEntity
{
    use HasMedias, HasTypedEntity, KeepsSubclassJsonDataRelations;

    protected $table = 'blocks';

    protected $fillable = ['handle', 'type', 'data'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    protected $entitiesByType = [];

    /**
     * The references of a block's data: its image and its blocks, the paths of the 1.x
     * declarations, as relations (see `Page::jsonDataSchema()`). The root keeps the keys it
     * doesn't name.
     */
    public function jsonDataSchema(string $attribute): ?ObjectType
    {
        if ($attribute !== 'data' || $this->overridesJsonDataRelations()) {
            return null;
        }

        return Json::object([
            'image' => Json::relation('medias'),
            'blocks' => Json::list(Json::relation('blocks')),
        ])->additional(true);
    }

    /**
     * @deprecated The data is declared by `jsonDataSchema()`, which gives the same paths. A
     * subclass that overrides this method keeps its declarations; removed in 3.0.
     */
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
