<?php

namespace Folklore\Contracts\Entities;

use Illuminate\Support\Collection;

interface Page extends Entity, HasBlocks, Pageable
{
    public function handle(): ?string;

    public function title(string $locale): string;

    public function slug(string $locale): ?string;

    public function description(string $locale): ?string;

    public function image(): ?Image;

    public function parent(): ?Page;

    public function children(): Collection;
}
