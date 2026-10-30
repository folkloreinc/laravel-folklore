<?php

namespace App\Entities;

use App\Contracts\Resources\Wine as WineContract;
use Folklore\Eloquent\JsonData;
use Folklore\Entities\ModelEntity;
use Panneau\Contracts\ResourceItem;

class Wine extends ModelEntity implements ResourceItem, WineContract
{
    public function references(): array
    {
        return JsonData::map([]);
    }
}

class Tag implements ResourceItem
{
    public function id(): string
    {
        return '1';
    }
}
-----
<?php

namespace App\Entities;

use App\Contracts\Resources\Wine as WineContract;
use Folklore\Eloquent\JsonData;
use Folklore\Entities\ModelEntity;
use Panneau\Contracts\ResourceItem;

class Wine extends ModelEntity implements WineContract
{
    public function references(): array
    {
        return \Folklore\JsonData\JsonData::map([]);
    }
}

class Tag implements \Folklore\Contracts\Entities\Entity
{
    public function id(): string
    {
        return '1';
    }
}
