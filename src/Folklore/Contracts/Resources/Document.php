<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Medias\Document as MediasDocument;

interface Document extends Media, MediasDocument
{
    public function metadata(): DocumentMetadata;
}
