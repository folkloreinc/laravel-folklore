<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Medias\Video as MediasVideo;

interface Video extends Media, MediasVideo
{
    public function metadata(): VideoMetadata;
}
