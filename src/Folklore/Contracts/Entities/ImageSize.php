<?php

namespace Folklore\Contracts\Entities;

interface ImageSize
{
    public function id(): string;

    public function url(): string;

    public function width(): int;

    public function height(): int;

    public function mime(): ?string;
}
