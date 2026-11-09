<?php

namespace Folklore\Entities\Blocks;

use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Pages\DeclaresBlockFields;
use Folklore\Entities\Block;
use Folklore\Entities\Blocks\Concerns\ReadsBlockData;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;
use Panneau\Fields\Image;
use Panneau\Fields\RichText;
use Panneau\Fields\Text;

/**
 * An image of the media library, with a caption and credits, both translatable. A standard
 * block type (docs/decisions/0025).
 */
class ImageBlock extends Block implements DeclaresBlockFields
{
    use ReadsBlockData;

    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            'image' => self::mediaNode(),
            'caption' => Json::string()->nullable()->translatable(),
            'credits' => Json::string()->nullable()->translatable(),
        ])->additional(false);
    }

    public static function blockLabel(): string
    {
        return self::adminLabel('blocks.image');
    }

    public static function blockIcon(): ?string
    {
        return 'image';
    }

    public static function blockFields(): array
    {
        return [
            Image::make('image')->label(self::adminLabel('fields.image')),
            RichText::make('caption')->localized()->label(self::adminLabel('fields.caption')),
            Text::make('credits')->localized()->label(self::adminLabel('fields.credits')),
        ];
    }

    /**
     * The image, which the page's cast resolved from its reference, with the edits chosen for
     * this block (its crop…), which its URL and sizes render.
     */
    public function image(): ?ImageContract
    {
        return $this->imageAt('image');
    }

    public function caption(?string $locale = null): ?string
    {
        return $this->translated('caption', $locale);
    }

    public function credits(?string $locale = null): ?string
    {
        return $this->translated('credits', $locale);
    }
}
