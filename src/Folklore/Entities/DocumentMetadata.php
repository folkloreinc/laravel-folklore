<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\DocumentMetadata as DocumentMetadataContract;

class DocumentMetadata extends MediaMetadata implements DocumentMetadataContract
{
    public function pagesCount(): ?int
    {
        $metadata = $this->getMetadatas()->get('pages_count');
        return !is_null($metadata) ? $metadata->getValue() : null;
    }
}
