<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Resources\Document as DocumentResource;

interface Documents extends Resources
{
    public function findById(string $id): ?DocumentResource;

    public function findByHandle(string $handle): ?DocumentResource;

    public function create($data): DocumentResource;

    public function update(string $id, $data): ?DocumentResource;
}
