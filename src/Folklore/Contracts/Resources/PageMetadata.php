<?php

namespace Folklore\Contracts\Resources;

interface PageMetadata
{
    public function url(string $locale): string;

    public function canonical(string $locale): string;

    public function title(string $locale): ?string;

    public function description(string $locale): ?string;

    public function image(string $locale): ?Image;
}
