<?php

// The contracts of laravel-panneau 1.x, installed on a site while it upgrades from 1.1

namespace Panneau\Contracts;

interface ResourceItem
{
    public function id(): string;
}

interface Repository
{
    public function findById(string $id): ?ResourceItem;

    public function get(array $query = [], ?int $page = null, ?int $count = 10);

    public function create(array $data): ResourceItem;

    public function update(string $id, array $data): ?ResourceItem;

    public function destroy(string $id): bool;
}
