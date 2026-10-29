<?php

namespace App\Repositories\Concerns;

use Panneau\Contracts\ResourceItem;

trait WritesReferences
{
    protected function reference($item, string $prefix): ?string
    {
        $id = $item instanceof ResourceItem ? $item->id() : $item;

        return ! empty($id) ? $prefix.'://'.$id : null;
    }
}
-----
<?php

namespace App\Repositories\Concerns;

use Folklore\Contracts\Entities\Entity;

trait WritesReferences
{
    protected function reference($item, string $prefix): ?string
    {
        $id = $item instanceof Entity ? $item->id() : $item;

        return ! empty($id) ? $prefix.'://'.$id : null;
    }
}
