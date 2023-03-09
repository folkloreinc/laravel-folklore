<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Illuminate\Support\Collection;

interface HasCustomerData
{
    public function getCustomerData(array $data, ?Customer $existing = null): ?array;
}
