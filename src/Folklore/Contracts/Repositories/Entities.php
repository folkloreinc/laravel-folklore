<?php

namespace Folklore\Contracts\Repositories;

use Panneau\Contracts\Repository;
use Folklore\Contracts\Entities\Entity;

interface Entities extends Repository
{
    public function findById(string $id): ?Entity;

    public function get(array $query = [], ?int $page = null, ?int $count = 10);

    public function count(array $params = []): int;

    public function has(array $params = []): bool;

    public function pluck($column, array $query = [], ?int $page = null, ?int $count = 10);

    public function value($column, array $query = []);

    public function create($data): Entity;

    public function update(string $id, $data): ?Entity;

    public function destroy(string $id): bool;

    public function setGlobalQuery(array $query);
}
