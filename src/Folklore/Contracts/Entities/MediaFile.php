<?php

namespace Folklore\Contracts\Entities;

use Panneau\Contracts\ResourceItem;

interface MediaFile extends Entity, ResourceItem
{
    public function id(): string;

    public function handle(): ?string;

    public function name(): ?string;

    public function url(): string;

    public function mime(): ?string;

    public function size(): ?int;
}
