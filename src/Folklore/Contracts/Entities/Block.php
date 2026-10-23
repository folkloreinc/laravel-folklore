<?php

namespace Folklore\Contracts\Entities;

interface Block extends Entity
{
    public function type(): string;

    public function data(): ?array;
}
