<?php

namespace Folklore\Contracts\Repositories;

use Panneau\Contracts\Repository;
use Folklore\Contracts\Resources\Resource;

interface Resources extends Repository
{
    public function findById(string $id): ?Resource;

    public function get(array $query = [], ?int $page = null, ?int $count = 10);

    public function count(array $params = []): int;

    public function has(array $params = []): bool;

    public function pluck($column, array $query = [], ?int $page = null, ?int $count = 10);

    public function create($data): Resource;

    public function update(string $id, $data): ?Resource;

    public function destroy(string $id): bool;

    public function setGlobalQuery(array $query);
}
