<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Metadatas\Medias\AudioMetadata as MediasAudioMetadata;

interface AudioMetadata extends MediaMetadata, MediasAudioMetadata
{
    public function duration(): float;
}
