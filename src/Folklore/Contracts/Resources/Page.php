<?php

namespace Folklore\Contracts\Resources;

use Illuminate\Support\Collection;
use Panneau\Contracts\ResourceItem;

interface Page extends Resource, ResourceItem, Pageable, HasBlocks
{
    public function handle(): ?string;

    public function title(string $locale): string;

    public function slug(string $locale): ?string;

    public function description(string $locale): ?string;

    public function image(): ?Image;

    public function parent(): ?Page;

    public function children(): Collection;
}
