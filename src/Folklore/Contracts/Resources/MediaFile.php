<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Medias\MediaFile as MediasMediaFile;
use Contenu\Contracts\Medias\Source;
use Panneau\Contracts\ResourceItem;

interface MediaFile extends ResourceItem, Resource, MediasMediaFile
{
    public function id(): string;

    public function handle(): ?string;

    public function name(): ?string;

    public function url(): string;

    public function mime(): ?string;

    public function size(): ?int;
}
