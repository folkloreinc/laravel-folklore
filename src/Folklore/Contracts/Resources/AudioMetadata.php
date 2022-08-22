<?php

namespace Folklore\Contracts\Resources;

interface AudioMetadata extends MediaMetadata
{
    public function duration(): float;
}
