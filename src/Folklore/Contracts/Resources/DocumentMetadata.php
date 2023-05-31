<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Metadatas\Medias\DocumentMetadata as MediasDocumentMetadata;

interface DocumentMetadata extends MediaMetadata, MediasDocumentMetadata
{
    public function pagesCount(): ?int;
}
