<?php

namespace Folklore\Entities\Blocks\Concerns;

use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\Type;
use Illuminate\Container\Container;

/**
 * Reads the values of a block's data, for the standard block types.
 */
trait ReadsBlockData
{
    /**
     * A string of the data.
     */
    protected function string(string $key): ?string
    {
        $value = data_get($this->data, $key);

        return is_string($value) ? $value : null;
    }

    /**
     * A translatable string of the data, in a locale: the current one by default.
     */
    protected function translated(string $key, ?string $locale = null): ?string
    {
        $current = Container::getInstance()->make('config')->get('app.locale');
        $locale ??= is_string($current) ? $current : '';
        $value = data_get($this->data, $key);
        if (is_array($value)) {
            $value = $value[$locale] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * The node of a media in the schema: `Json::media()` with the medias module, or else the
     * 1.x reference of the `medias` relation, as the pages' own image.
     */
    protected static function mediaNode(): Type
    {
        $media = Json::hasMacro('media') ? Json::__callStatic('media', []) : null;

        return $media instanceof Type ? $media : Json::relation('medias');
    }

    /**
     * A translated label of the package (`folklore::panneau.*`), for the admin.
     */
    protected static function adminLabel(string $key): string
    {
        $label = __('folklore::panneau.'.$key);

        return is_string($label) ? $label : $key;
    }
}
