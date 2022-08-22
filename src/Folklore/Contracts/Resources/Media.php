<?php

namespace Folklore\Contracts\Resources;

use Folklore\Contracts\Resources\Resource;
use Illuminate\Support\Collection;

interface Media extends Resource
{
    public function url(): string;

    public function metadata(): MediaMetadata;

    public function thumbnailUrl(): ?string;

    public function files(): Collection;
}
