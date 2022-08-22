<?php

namespace Folklore\Contracts\Resources;

interface Video extends Media
{
    public function metadata(): VideoMetadata;
}
