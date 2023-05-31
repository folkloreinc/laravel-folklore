<?php

namespace Folklore\Contracts\Resources;

use Contenu\Contracts\Medias\Audio as MediasAudio;

interface Audio extends Media, MediasAudio
{
    public function metadata(): AudioMetadata;
}
