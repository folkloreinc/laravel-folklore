<?php

namespace Folklore\Contracts\Entities;

interface DocumentMetadata extends MediaMetadata
{
    public function pagesCount(): ?int;
}
