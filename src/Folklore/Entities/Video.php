<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\Video as VideoContract;
use Folklore\Contracts\Entities\VideoMetadata as VideoMetadataContract;

class Video extends Media implements VideoContract
{
    public function metadata(): VideoMetadataContract
    {
        return once(fn() => new VideoMetadata($this->model));
    }
}
