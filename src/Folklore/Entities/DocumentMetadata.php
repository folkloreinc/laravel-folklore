<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\DocumentMetadata as DocumentMetadataContract;

class DocumentMetadata extends MediaMetadata implements DocumentMetadataContract
{
    public function pagesCount(): ?int
    {
        $pagesCount = $this->value('pages_count');

        return is_numeric($pagesCount) ? (int) $pagesCount : null;
    }
}
