<?php

namespace App\Entities;

use App\Contracts\Resources\Wine as WineContract;
use Folklore\Entities\ModelEntity;
use Illuminate\Contracts\Support\Arrayable;
use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;

class Wine extends ModelEntity implements ResourceItem, WineContract {}

class Tag implements Arrayable, ResourceItem
{
    public function id(): string
    {
        return '1';
    }

    public function toArray(): array
    {
        return [];
    }
}

// Folklore's `Entities` asks for more methods
abstract class LegacyRepository implements Repository {}
-----
<?php

namespace App\Entities;

use Folklore\Contracts\Entities\Entity;
use App\Contracts\Resources\Wine as WineContract;
use Folklore\Entities\ModelEntity;
use Illuminate\Contracts\Support\Arrayable;
use Panneau\Contracts\Repository;

class Wine extends ModelEntity implements WineContract {}

class Tag implements Arrayable, Entity
{
    public function id(): string
    {
        return '1';
    }

    public function toArray(): array
    {
        return [];
    }
}

// Folklore's `Entities` asks for more methods
abstract class LegacyRepository implements Repository {}
