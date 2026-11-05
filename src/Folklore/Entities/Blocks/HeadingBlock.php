<?php

namespace Folklore\Entities\Blocks;

use Folklore\Contracts\Pages\DeclaresBlockFields;
use Folklore\Entities\Block;
use Folklore\Entities\Blocks\Concerns\ReadsBlockData;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;
use Panneau\Fields\Text;

/**
 * A heading: a title that divides the page, translatable. A standard block type
 * (docs/decisions/0025).
 */
class HeadingBlock extends Block implements DeclaresBlockFields
{
    use ReadsBlockData;

    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            'title' => Json::string()->nullable()->translatable(),
        ])->additional(false);
    }

    public static function blockLabel(): string
    {
        return self::adminLabel('blocks.heading');
    }

    public static function blockIcon(): ?string
    {
        return 'heading';
    }

    public static function blockFields(): array
    {
        return [
            Text::make('title')->localized()->label(self::adminLabel('fields.title')),
        ];
    }

    public function title(?string $locale = null): ?string
    {
        return $this->translated('title', $locale);
    }
}
