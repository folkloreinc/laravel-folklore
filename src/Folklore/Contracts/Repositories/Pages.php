<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Entities\Page;

interface Pages extends Entities
{
    public function findById(string $id): ?Page;

    public function findByHandle(string $handle): ?Page;

    public function findBySlug(string $slug, string $locale = null): ?Page;

    public function create($data): Page;

    public function update(string $id, $data): ?Page;
}
