<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures\Schemas;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Folklore\Tests\Feature\Eloquent\Fixtures\Prize;
use Illuminate\Database\Eloquent\Model;

/**
 * A 1.1 contest whose declarations a schema can't give the same behaviour: `prizes://` isn't
 * mapped, and `links.*` deletes the media it no longer references.
 */
class LegacyContest extends Model implements HasJsonDataRelations
{
    use HasMedias;

    protected $table = 'contests';

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return [
            'logo' => 'medias',
            'prizes.*' => 'prizes',
            'links.*' => [
                'relation' => 'medias',
                'delete' => true,
            ],
        ];
    }

    public function prizes()
    {
        return $this->hasMany(Prize::class, 'contest_id');
    }
}
