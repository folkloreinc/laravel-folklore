<?php

namespace App\Contracts\Resources;

use App\Missing\BaseEntity;
use Folklore\Contracts\Entities\Entity as EntityContract;
use Panneau\Contracts\ResourceItem as PanneauItem;

interface Wine extends \Panneau\Contracts\ResourceItem, \Countable {}

interface Winemaker extends EntityContract, PanneauItem {}

interface Order extends PanneauItem, EntityContract {}

class Location implements PanneauItem
{
    public function id(): string
    {
        return '1';
    }
}

// A parent that doesn't load
class Saq extends BaseEntity implements PanneauItem {}

function location(): object
{
    return new class implements PanneauItem
    {
        public function id(): string
        {
            return '1';
        }
    };
}
-----
<?php

namespace App\Contracts\Resources;

use App\Missing\BaseEntity;
use Folklore\Contracts\Entities\Entity as EntityContract;

interface Wine extends EntityContract, \Countable {}

interface Winemaker extends EntityContract {}

interface Order extends EntityContract {}

class Location implements EntityContract
{
    public function id(): string
    {
        return '1';
    }
}

// A parent that doesn't load
class Saq extends BaseEntity implements EntityContract {}

function location(): object
{
    return new class implements EntityContract
    {
        public function id(): string
        {
            return '1';
        }
    };
}
