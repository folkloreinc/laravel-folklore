<?php

namespace Folklore\Contracts\Entities;

interface Video extends Media
{
    public function metadata(): VideoMetadata;
}
