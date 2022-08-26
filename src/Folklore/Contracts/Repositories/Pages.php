<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Resources\Page as PageResource;

interface Pages extends Resources
{
    public function findById(string $id): ?PageResource;

    public function findByHandle(string $handle): ?PageResource;

    public function findBySlug(string $slug, string $locale = null): ?PageResource;

    public function create($data): PageResource;

    public function update(string $id, $data): ?PageResource;
}
