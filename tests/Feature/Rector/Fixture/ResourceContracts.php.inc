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

// `Entities` asks for more methods, with other signatures: the classes that implement it may not have them
interface Wines extends Repository {}
-----
<?php

namespace App\Contracts\Resources;

use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Repositories\Entities;
use Panneau\Contracts\Repository;

interface Order extends Entity
{
    public function handle(): ?string;
}

interface SaqLocation extends Entity {}

interface Article extends PageContract {}

interface Orders extends Entities {}

// `Entities` asks for more methods, with other signatures: the classes that implement it may not have them
interface Wines extends Repository {}
