<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Medias\ImageSize as MediasImageSize;
use Panneau\Contracts\ResourceItem;
use Illuminate\Support\Collection;

interface ImageSize extends MediasImageSize
{
    public function id(): string;

    public function url(): string;

    public function width(): int;

    public function height(): int;
}
