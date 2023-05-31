<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\DocumentMetadata as DocumentMetadataContract;

class DocumentMetadata extends MediaMetadata implements DocumentMetadataContract
{
    public function pagesCount(): ?int
    {
        $metadata = $this->getMetadatas()->get('pages_count');
        return !is_null($metadata) ? $metadata->getValue() : null;
    }

    public function pages(): int
    {
        return $this->pagesCount() ?? 0;
    }
}
