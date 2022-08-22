<?php

namespace Folklore\Contracts\Resources;

interface MediaMetadata
{
    public function filename(): ?string;

    public function size(): ?int;

    public function mime(): ?string;

    public function description(): ?string;
}
