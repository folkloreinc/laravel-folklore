<?php

namespace Folklore\Contracts\Resources;

interface DocumentMetadata extends MediaMetadata
{
    public function pagesCount(): ?int;
}
