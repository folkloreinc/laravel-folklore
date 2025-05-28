<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\VideoMetadata as VideoMetadataContract;

class VideoMetadata extends MediaMetadata implements VideoMetadataContract
{
    public function width(): int
    {
        return once(function () {
            $metadata = $this->getMetadatas()->get('width');
            return !is_null($metadata) ? $metadata->getValue() : 0;
        });
    }

    public function height(): int
    {
        return once(function () {
            $metadata = $this->getMetadatas()->get('height');
            return !is_null($metadata) ? $metadata->getValue() : 0;
        });
    }

    public function duration(): float
    {
        return once(function () {
            $metadata = $this->getMetadatas()->get('duration');
            return !is_null($metadata) ? $metadata->getValue() : 0;
        });
    }
}
