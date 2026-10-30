<?php

namespace App\Repositories;

use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;

// A site's repository contract on Panneau's alone, implemented without Folklore's base repository
interface External extends Repository {}

class ExternalRepository implements External
{
    public function findById(string $id): ?ResourceItem
    {
        return null;
    }

    public function get(array $query = [], ?int $page = null, ?int $count = 10)
    {
        return [];
    }

    public function create(array $data): ResourceItem
    {
        throw new \RuntimeException();
    }

    public function update(string $id, array $data): ?ResourceItem
    {
        return null;
    }

    public function destroy(string $id): bool
    {
        return false;
    }
}
-----
<?php

namespace App\Repositories;

use Folklore\Contracts\Entities\Entity;
use Panneau\Contracts\Repository;

// A site's repository contract on Panneau's alone, implemented without Folklore's base repository
interface External extends Repository {}

class ExternalRepository implements External
{
    public function findById(string $id): ?Entity
    {
        return null;
    }

    public function get(array $query = [], ?int $page = null, ?int $count = 10)
    {
        return [];
    }

    public function create(array $data): Entity
    {
        throw new \RuntimeException();
    }

    public function update(string $id, array $data): ?Entity
    {
        return null;
    }

    public function destroy(string $id): bool
    {
        return false;
    }
}
