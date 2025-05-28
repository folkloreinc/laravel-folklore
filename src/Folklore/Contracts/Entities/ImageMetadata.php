<?php

namespace Folklore\Contracts\Entities;

interface ImageMetadata extends MediaMetadata
{
    public function width(): int;

    public function height(): int;
}
