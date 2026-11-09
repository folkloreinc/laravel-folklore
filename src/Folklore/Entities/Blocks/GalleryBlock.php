<?php

namespace Folklore\Entities\Blocks;

use Folklore\Contracts\Entities\Image as ImageContract;
use Folklore\Contracts\Pages\DeclaresBlockFields;
use Folklore\Entities\Block;
use Folklore\Entities\Blocks\Concerns\ReadsBlockData;
use Folklore\Entities\Image;
use Folklore\JsonData\Schema\Json;
use Folklore\JsonData\Schema\ObjectType;
use Folklore\Pages\BlockLevel;
use Illuminate\Support\Collection;
use Panneau\Fields\Image as ImageField;

/**
 * Images of the media library. A standard block type (docs/decisions/0025).
 */
class GalleryBlock extends Block implements DeclaresBlockFields
{
    use ReadsBlockData;

    public static function blockSchema(BlockLevel $level): ObjectType
    {
        return Json::object([
            'images' => Json::list(self::mediaNode()),
        ])->additional(false);
    }

    public static function blockLabel(): string
    {
        return self::adminLabel('blocks.gallery');
    }

    public static function blockIcon(): ?string
    {
        return 'images';
    }

    public static function blockFields(): array
    {
        return [
            ImageField::make('images')->multiple()->label(self::adminLabel('fields.images')),
        ];
    }

    /**
     * The images, which the page's cast resolved from their references, with the edits chosen
     * for this block (their crop…), which their URLs and sizes render.
     *
     * @return Collection<int, ImageContract>
     */
    public function images(): Collection
    {
        $images = [];
        foreach ((array) data_get($this->data, 'images', []) as $image) {
            $image = Image::fromValue($image);
            if ($image !== null) {
                $images[] = $image;
            }
        }

        return collect($images);
    }
}
