<?php

namespace Folklore\Contracts\Resources;

use Illuminate\Support\Collection;

interface Image extends Media
{
    public function metadata(): ImageMetadata;

    public function sizes(): Collection;

    public function setFilters($filters): Image;
}
