<?php

namespace Folklore\Contracts\Entities;

use Illuminate\Support\Collection;

interface Media extends Entity
{
    public function url(): string;

    public function metadata(): MediaMetadata;

    public function thumbnailUrl(): ?string;

    public function files(): Collection;
}
