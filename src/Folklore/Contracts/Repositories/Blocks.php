<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Entities\Block;

interface Blocks extends Entities
{
    public function findById(string $id): ?Block;

    public function findByHandle(string $handle): ?Block;

    public function create($data): Block;

    public function update(string $id, $data): ?Block;
}
