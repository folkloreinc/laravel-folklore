<?php

namespace Folklore\Entities\Blocks;

use Folklore\Contracts\Pages\DeclaresBlockFields;
use Folklore\Entities\Block;
use Folklore\Entities\Blocks\Concerns\ReadsBlockData;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;
use Panneau\Fields\Boolean;
use Panneau\Fields\Text;
use Panneau\Fields\Url;

/**
 * A button: a label and a URL, both translatable, and whether it leads out of the site. A
 * standard block type (docs/decisions/0025).
 */
class ButtonBlock extends Block implements DeclaresBlockFields
{
    use ReadsBlockData;

    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            'label' => Json::string()->nullable()->translatable(),
            'url' => Json::string()->nullable()->translatable(),
            'external' => Json::boolean()->nullable(),
        ])->additional(false);
    }

    public static function blockLabel(): string
    {
        return self::adminLabel('blocks.button');
    }

    public static function blockIcon(): ?string
    {
        return 'link';
    }

    public static function blockFields(): array
    {
        return [
            Text::make('label')->localized()->label(self::adminLabel('fields.label')),
            Url::make('url')->localized()->label(self::adminLabel('fields.url')),
            Boolean::make('external')->label(self::adminLabel('fields.external')),
        ];
    }

    public function label(?string $locale = null): ?string
    {
        return $this->translated('label', $locale);
    }

    public function url(?string $locale = null): ?string
    {
        return $this->translated('url', $locale);
    }

    public function external(): bool
    {
        return data_get($this->data, 'external') === true;
    }
}
