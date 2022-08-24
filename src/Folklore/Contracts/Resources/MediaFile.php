<?php

namespace Folklore\Contracts\Resources;

use Panneau\Contracts\ResourceItem;

interface MediaFile extends ResourceItem, Resource
{
    public function id(): string;

    public function handle(): ?string;

    public function name(): ?string;

    public function url(): string;

    public function mime(): ?string;

    public function size(): ?int;
}
