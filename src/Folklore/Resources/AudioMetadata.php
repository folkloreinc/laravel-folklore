<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\AudioMetadata as AudioMetadataContract;

class AudioMetadata extends MediaMetadata implements AudioMetadataContract
{
    public function duration(): float
    {
        $metadata = $this->getMetadatas()->get('duration');
        return !is_null($metadata) ? $metadata->getValue() : 0;
    }
}
