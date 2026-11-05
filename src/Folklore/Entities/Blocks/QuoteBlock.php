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
 * A quote, translatable, and its author. A standard block type (docs/decisions/0025).
 */
class QuoteBlock extends Block implements DeclaresBlockFields
{
    use ReadsBlockData;

    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            'body' => Json::string()->nullable()->translatable(),
            'author' => Json::string()->nullable(),
        ])->additional(false);
    }

    public static function blockLabel(): string
    {
        return self::adminLabel('blocks.quote');
    }

    public static function blockIcon(): ?string
    {
        return 'quote';
    }

    public static function blockFields(): array
    {
        return [
            RichText::make('body')->localized()->label(self::adminLabel('fields.quote')),
            Text::make('author')->label(self::adminLabel('fields.author')),
        ];
    }

    public function body(?string $locale = null): ?string
    {
        return $this->translated('body', $locale);
    }

    public function author(): ?string
    {
        return $this->string('author');
    }
}
