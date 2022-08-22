<?php

namespace  Folklore\Resources;

use Folklore\Contracts\Resources\Video as VideoContract;
use Folklore\Contracts\Resources\VideoMetadata as VideoMetadataContract;

class Video extends Media implements VideoContract
{
    public function metadata(): VideoMetadataContract
    {
        if (!isset($this->metadata)) {
            $this->metadata = new VideoMetadata($this->model);
        }
        return $this->metadata;
    }
}
