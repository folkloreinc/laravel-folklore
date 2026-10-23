<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\AudioMetadata as AudioMetadataContract;

class AudioMetadata extends MediaMetadata implements AudioMetadataContract
{
    public function duration(): float
    {
        return (float) ($this->getOriginalFile()?->duration() ?? $this->number('duration') ?? 0);
    }
}
