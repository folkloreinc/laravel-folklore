<?php

namespace Folklore\Contracts\Resources;

interface Audio extends Media
{
    public function metadata(): AudioMetadata;
}
