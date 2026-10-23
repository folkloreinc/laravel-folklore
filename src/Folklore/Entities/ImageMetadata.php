<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\ImageMetadata as ImageMetadataContract;

class ImageMetadata extends MediaMetadata implements ImageMetadataContract
{
    public function width(): int
    {
        return (int) ($this->getOriginalFile()?->width() ?? $this->number('width') ?? 0);
    }

    public function height(): int
    {
        return (int) ($this->getOriginalFile()?->height() ?? $this->number('height') ?? 0);
    }
}
