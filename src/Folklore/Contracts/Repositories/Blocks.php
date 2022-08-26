<?php

namespace Folklore\Contracts\Repositories;

use Panneau\Contracts\Repository;
use Folklore\Contracts\Resources\Block as BlockResource;

interface Blocks extends Repository, Resources
{
    public function findById(string $id): ?BlockResource;

    public function findByHandle(string $handle): ?BlockResource;

    public function create($data): BlockResource;

    public function update(string $id, $data): ?BlockResource;
}
