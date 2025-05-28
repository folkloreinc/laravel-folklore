<?php

namespace Folklore\Contracts\Entities;

interface Pageable
{
    public function pageType(): string;

    public function published(): bool;

    public function url(string $locale, bool $absolute = false): string;

    public function metadata(): PageMetadata;
}
