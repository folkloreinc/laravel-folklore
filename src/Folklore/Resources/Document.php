<?php

namespace Folklore\Resources;

use Folklore\Contracts\Resources\Document as DocumentContract;
use Folklore\Contracts\Resources\DocumentMetadata as DocumentMetadataContract;
use Illuminate\Support\Collection;

class Document extends Media implements DocumentContract
{
    public function metadata(): DocumentMetadataContract
    {
        if (!isset($this->metadata)) {
            $this->metadata = new DocumentMetadata($this->model);
        }
        return $this->metadata;
    }
}
