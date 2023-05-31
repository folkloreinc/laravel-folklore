<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Metadatas\Medias\VideoMetadata as MediasVideoMetadata;

interface VideoMetadata extends MediaMetadata, MediasVideoMetadata
{
    public function width(): int;

    public function height(): int;

    public function duration(): float;
}
