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

    protected function url(string $host, string $scheme = 'https'): string
    {
        return $scheme.'://'.$host;
    }

    protected function secureUrl(string $host): string
    {
        return 'https://'.$host;
    }
}
