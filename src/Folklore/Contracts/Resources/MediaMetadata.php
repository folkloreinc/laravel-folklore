<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Metadatas\Medias\MediaMetadata as MediasMediaMetadata;

interface MediaMetadata extends MediasMediaMetadata
{
    public function filename(): ?string;

    public function size(): ?int;

    public function mime(): ?string;

    public function description(): ?string;
}
