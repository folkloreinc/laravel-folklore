<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures\Schemas;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Folklore\Tests\Feature\Eloquent\Fixtures\Prize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A 1.1 contest that owns its prizes, whose key `prizes://` isn't mapped, deletes those
 * it no longer references, and declares `menus.*` and `menus.fr.*`, which aren't one tree.
 */
class OwningContest extends Model implements HasJsonDataRelations
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
            'prizes.*' => ['relation' => 'prizes', 'delete' => true],
            'menus.*' => 'medias',
            'menus.fr.*' => 'medias',
        ];
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(Prize::class, 'contest_id');
    }
}
