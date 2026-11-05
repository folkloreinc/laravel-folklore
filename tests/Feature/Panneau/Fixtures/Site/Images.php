<?php

namespace Folklore\Tests\Feature\Panneau\Fixtures\Site;

use Folklore\Panneau\Resources\Medias;

/**
 * A site's library of images only: it overrides the types of the media library.
 */
class Images extends Medias
{
    public function mediaTypes(): array
    {
        return ['image'];
    }
}
