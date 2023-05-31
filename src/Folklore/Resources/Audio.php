<?php

namespace  Folklore\Resources;

use Folklore\Contracts\Resources\Audio as AudioContract;
use Folklore\Contracts\Resources\AudioMetadata as AudioMetadataContract;

class Audio extends Media implements AudioContract
{
    protected $metadata;

    public function metadata(): AudioMetadataContract
    {
        if (!isset($this->metadata)) {
            $this->metadata = new AudioMetadata($this->model);
        }
        return $this->metadata;
    }
}
