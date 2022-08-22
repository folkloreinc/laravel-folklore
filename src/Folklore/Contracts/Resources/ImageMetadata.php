<?php

namespace Folklore\Contracts\Resources;

interface ImageMetadata extends MediaMetadata
{
    public function width(): int;

    public function height(): int;
}
