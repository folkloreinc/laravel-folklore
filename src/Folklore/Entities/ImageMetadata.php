<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\ImageMetadata as ImageMetadataContract;

class ImageMetadata extends MediaMetadata implements ImageMetadataContract
{
    public function width(): int
    {
        $metadata = $this->getMetadatas()->get('width');
        return !is_null($metadata) ? $metadata->getValue() : 0;
    }

    public function height(): int
    {
        $metadata = $this->getMetadatas()->get('height');
        return !is_null($metadata) ? $metadata->getValue() : 0;
    }
}
