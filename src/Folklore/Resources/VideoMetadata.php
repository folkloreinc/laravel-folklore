<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\VideoMetadata as VideoMetadataContract;

class VideoMetadata extends MediaMetadata implements VideoMetadataContract
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

    public function duration(): float
    {
        $metadata = $this->getMetadatas()->get('duration');
        return !is_null($metadata) ? $metadata->getValue() : 0;
    }
}
