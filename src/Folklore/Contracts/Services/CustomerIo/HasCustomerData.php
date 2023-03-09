<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Illuminate\Support\Collection;

interface HasCustomerData
{
    public function toCustomerData(?Customer $existing = null): ?array;
}
