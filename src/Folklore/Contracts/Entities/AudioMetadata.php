<?php

namespace Folklore\Contracts\Entities;

interface AudioMetadata extends MediaMetadata
{
    public function duration(): float;
}
