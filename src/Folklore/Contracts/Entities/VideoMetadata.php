<?php

namespace Folklore\Contracts\Entities;

interface VideoMetadata extends MediaMetadata
{
    public function width(): int;

    public function height(): int;

    public function duration(): float;
}
