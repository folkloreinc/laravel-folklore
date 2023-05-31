<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Medias\Image as MediasImage;
use Illuminate\Support\Collection;

interface Image extends Media, MediasImage
{
    public function metadata(): ImageMetadata;

    public function sizes(): Collection;
}
