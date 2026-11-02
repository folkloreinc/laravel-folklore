<?php

namespace Folklore\Tests\Feature\Eloquent\Fixtures;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Illuminate\Database\Eloquent\Model;

/**
 * A block that holds blocks and images in its JSON data, declared as in 1.x, as the pages
 * of a 1.1 site hold blocks that hold blocks and media (#153).
 */
class NestedBlock extends Model implements HasJsonDataRelations, ToEntity
{
    use HasMedias;

    protected $table = 'nested_blocks';

    protected $fillable = ['type'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [
            'blocks.*' => 'blocks',
            'image.*' => 'medias',
        ];
    }

    public function blocks()
    {
        return $this->morphToMany(self::class, 'blockable', 'nested_blocks_pivot', 'blockable_id', 'nested_block_id');
    }

    public function toEntity(): FixtureEntity
    {
        return new FixtureEntity($this);
    }
}
