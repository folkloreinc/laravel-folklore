<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures\Schemas;

use Folklore\Contracts\Eloquent\HasJsonDataColumnExtract;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Contracts\Entities\ToEntity;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Folklore\Tests\Feature\Eloquent\Fixtures\FixtureEntity;
use Folklore\Tests\Feature\Eloquent\Fixtures\Prize;
use Illuminate\Database\Eloquent\Model;

/**
 * A 1.1 game whose JSON declarations are literal arrays: `folklore:upgrade --schemas`
 * converts them to `jsonDataSchema()`.
 */
class LegacyGame extends Model implements HasJsonDataColumnExtract, HasJsonDataRelations, ToEntity
{
    use HasMedias;

    protected $table = 'games';

    protected $fillable = ['handle'];

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    /**
     * The references of the JSON data.
     */
    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [
            'image.*' => 'medias',
            'gallery.*' => 'medias',
            'sponsors.*.logo' => 'medias',
            'cover' => [
                'relation' => 'medias',
                'lazy' => true,
            ],
            'prizes.*.prize' => 'prizes',
            'source' => ['relation' => 'prizes', 'sync' => false],
            'extras.*' => 'medias',
        ];
    }

    public function getJsonDataColumnExtract($key, $value, $attributes = [])
    {
        return [
            'title.fr' => 'title_fr',
            'title.en' => 'title_en',
        ];
    }

    public function prizes()
    {
        return $this->morphToMany(Prize::class, 'prizable', 'prizes_pivot')->withTimestamps();
    }

    public function toEntity(): FixtureEntity
    {
        return new FixtureEntity($this);
    }
}
