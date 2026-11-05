<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures;

use Panneau\Fields\Text;
use Panneau\Mediatheque\MediathequeResource;

/**
 * A library of images on laravel-mediatheque, for the `image` fields of blocks.
 */
class ImagesResource extends MediathequeResource
{
    public function mediaTypes(): array
    {
        return ['image'];
    }

    public function fields(): array
    {
        return [Text::make('name')];
    }
}
