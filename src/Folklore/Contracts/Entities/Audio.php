<?php

namespace Folklore\Contracts\Entities;

interface Audio extends Media
{
    public function metadata(): AudioMetadata;
}
