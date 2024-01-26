<?php

namespace Folklore\Contracts\Resources;

use Panneau\Contracts\ResourceItem;
use Illuminate\Support\Collection;

interface ImageSize
{
    public function id(): string;

    public function url(): string;

    public function width(): int;

    public function height(): int;

    public function mime(): ?string;
}
