<?php

namespace Folklore\Tests\Feature\Upgrade\Fixtures\Schemas;

use Folklore\Contracts\Eloquent\HasJsonDataColumnExtract;
use Folklore\Contracts\Eloquent\HasJsonDataRelations;
use Folklore\Eloquent\JsonDataCast;
use Folklore\Mediatheque\Concerns\HasMedias;
use Illuminate\Database\Eloquent\Model;

/**
 * A 1.1 game that extracts its title with a loop over the locales, as the contests
 * platform does: the converter writes the schema of what it returns.
 */
class LocalizedGame extends Model implements HasJsonDataColumnExtract, HasJsonDataRelations
{
    use HasMedias;

    protected $table = 'games';

    protected $casts = [
        'data' => JsonDataCast::class,
    ];

    public function getJsonDataRelations($key, $value, $attributes = [])
    {
        return ['cover' => 'medias'];
    }

    public function getJsonDataColumnExtract($key, $value, $attributes = [])
    {
        $locales = collect(config('locale.locales'));
        $columns = [];

        foreach ($locales as $locale) {
            $columns = array_merge($columns, ['title.'.$locale => 'title_'.$locale]);
        }

        return $columns;
    }
}
