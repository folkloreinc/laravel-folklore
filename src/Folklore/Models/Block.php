<?php

namespace Folklore\Models;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Entities\Block as BlockContract;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Entities\Block as BlockEntity;
use Folklore\Mediatheque\Concerns\HasMedias;
use Folklore\Support\Concerns\HasTypedEntity;
use Illuminate\Database\Eloquent\Model;

/**
 * A block stored as a row of `blocks`, linked through `blocks_pivot`, as in 1.x.
 *
 * @deprecated Blocks are stored in the page's JSON (docs/decisions/0023): kept for
 * `folklore:upgrade`, which converts these rows, and removed in 3.0.
 */
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

    /**
     * The entity of the block's object, as `folklore:upgrade` writes it in the page.
     */
    public function toEntity(): BlockContract
    {
        $data = $this->getAttribute('data');
        $key = $this->getKey();

        return new BlockEntity([
            'id' => is_scalar($key) ? (string) $key : '',
            'type' => $this->getAttribute('type'),
            'handle' => $this->getAttribute('handle'),
            ...(is_array($data) ? $data : []),
        ]);
    }
}
