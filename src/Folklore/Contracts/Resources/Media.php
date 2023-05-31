<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Media as ContractsMedia;
use Contenu\Contracts\Medias\Document;
use Folklore\Contracts\Resources\Resource;
use Illuminate\Support\Collection;

interface Media extends Resource, ContractsMedia
{
    public function url(): string;

    public function metadata(): MediaMetadata;

    public function thumbnailUrl(): ?string;

    public function files(): Collection;
}
