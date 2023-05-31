<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Metadatas\Medias\ImageMetadata as MediasImageMetadata;

interface ImageMetadata extends MediaMetadata, MediasImageMetadata
{
    public function width(): int;

    public function height(): int;
}
