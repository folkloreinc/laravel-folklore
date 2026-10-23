<?php

namespace Folklore\Contracts\Entities;

interface MediaFile extends Entity
{
    public function id(): string;

    public function handle(): ?string;

    public function name(): ?string;

    public function url(): string;

    public function mime(): ?string;

    public function size(): ?int;
}
