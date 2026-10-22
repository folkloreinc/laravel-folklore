<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Entities\Entity;

/**
 * The access to the data of one kind of entity.
 *
 * @template TEntity of Entity = Entity
 */
interface Entities
{
    /**
     * @return TEntity|null
     */
    public function findById(string $id): ?Entity;

    public function get(array $query = [], ?int $page = null, ?int $count = 10);

    public function count(array $params = []): int;

    public function has(array $params = []): bool;

    public function pluck($column, array $query = [], ?int $page = null, ?int $count = 10);

    public function value($column, array $query = []);

    /**
     * @return TEntity
     */
    public function create($data): Entity;

    /**
     * @return TEntity|null
     */
    public function update(string $id, $data): ?Entity;

    public function destroy(string $id): bool;

    public function setGlobalQuery(array $query);
}
