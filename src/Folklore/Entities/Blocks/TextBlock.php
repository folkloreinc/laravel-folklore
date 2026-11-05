<?php

namespace Folklore\Entities\Blocks;

use Folklore\Contracts\Pages\DeclaresBlockFields;
use Folklore\Entities\Block;
use Folklore\Entities\Blocks\Concerns\ReadsBlockData;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;
use Panneau\Fields\RichText;
use Panneau\Fields\Text;

/**
 * A text: a title and a body, both translatable. A standard block type
 * (docs/decisions/0025).
 */
class TextBlock extends Block implements DeclaresBlockFields
{
    use ReadsBlockData;

    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            'title' => Json::string()->nullable()->translatable(),
            'body' => Json::string()->nullable()->translatable(),
        ])->additional(false);
    }

    public static function blockLabel(): string
    {
        return self::adminLabel('blocks.text');
    }

    public static function blockIcon(): ?string
    {
        return 'text';
    }

    public static function blockFields(): array
    {
        return [
            Text::make('title')->localized()->label(self::adminLabel('fields.title')),
            RichText::make('body')->localized()->label(self::adminLabel('fields.body')),
        ];
    }

    public function title(?string $locale = null): ?string
    {
        return $this->translated('title', $locale);
    }

    public function body(?string $locale = null): ?string
    {
        return $this->translated('body', $locale);
    }
}
