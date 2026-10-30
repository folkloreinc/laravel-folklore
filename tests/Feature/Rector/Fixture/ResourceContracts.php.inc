<?php

namespace App\Contracts\Resources;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Repositories\Entities;
use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;

interface Order extends Entity, ResourceItem
{
    public function handle(): ?string;
}

interface SaqLocation extends ResourceItem {}

interface Article extends PageContract, ResourceItem {}

interface Orders extends Entities, Repository {}

interface Wines extends Repository {}
-----
<?php

namespace App\Contracts\Resources;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Repositories\Entities;

interface Order extends Entity
{
    public function handle(): ?string;
}

interface SaqLocation extends Entity {}

interface Article extends PageContract {}

interface Orders extends Entities {}

interface Wines extends Entities {}
