<?php

namespace Folklore\Contracts\Resources;

interface VideoMetadata extends MediaMetadata
{
    public function width(): int;

    public function height(): int;

    public function duration(): float;
}
