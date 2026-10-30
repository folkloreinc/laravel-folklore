<?php

namespace App\Repositories;

use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;

// A site's repository contract on Panneau's alone, implemented without Folklore's base
// repository: the methods of `Repository` keep `ResourceItem`, as 1.x's contract has it
interface External extends Repository
{
    public function findById(string $id): ?ResourceItem;
}

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

    // Not a method of `Repository`
    public function first(): ?ResourceItem
    {
        return null;
    }
}

class Events extends ExternalRepository
{
    public function findById(string $id): ?ResourceItem
    {
        return parent::findById($id);
    }
}
-----
<?php

namespace App\Repositories;

use Folklore\Contracts\Entities\Entity;
use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;

// A site's repository contract on Panneau's alone, implemented without Folklore's base
// repository: the methods of `Repository` keep `ResourceItem`, as 1.x's contract has it
interface External extends Repository
{
    public function findById(string $id): ?ResourceItem;
}

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

    // Not a method of `Repository`
    public function first(): ?Entity
    {
        return null;
    }
}

class Events extends ExternalRepository
{
    public function findById(string $id): ?ResourceItem
    {
        return parent::findById($id);
    }
}
