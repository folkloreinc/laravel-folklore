<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures\Schemas;

use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Illuminate\Database\Eloquent\Model;

/**
 * A 1.1 contest that builds its declarations: they are left to convert by hand.
 */
class BuiltContest extends Model implements HasJsonDataRelations
{
    use HasMedias;

    protected $table = 'contests';

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return array_merge(['logo' => 'medias'], $this->blockRelations());
    }

    /**
     * @return array<string, string>
     */
    protected function blockRelations(): array
    {
        return ['blocks.*.image' => 'medias'];
    }
}
