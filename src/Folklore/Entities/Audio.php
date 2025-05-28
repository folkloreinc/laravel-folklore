<?php

namespace  Folklore\Entities;

use Folklore\Contracts\Entities\Audio as AudioContract;
use Folklore\Contracts\Entities\AudioMetadata as AudioMetadataContract;

class Audio extends Media implements AudioContract
{
    public function metadata(): AudioMetadataContract
    {
        return once(function () {
            return new AudioMetadata($this->model);
        });
    }
}
