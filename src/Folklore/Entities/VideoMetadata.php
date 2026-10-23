<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\VideoMetadata as VideoMetadataContract;

class VideoMetadata extends MediaMetadata implements VideoMetadataContract
{
    public function width(): int
    {
        return (int) ($this->getOriginalFile()?->width() ?? $this->number('width') ?? 0);
    }

    public function height(): int
    {
        return (int) ($this->getOriginalFile()?->height() ?? $this->number('height') ?? 0);
    }

    public function duration(): float
    {
        return (float) ($this->getOriginalFile()?->duration() ?? $this->number('duration') ?? 0);
    }
}
