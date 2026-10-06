<?php

namespace Folklore\Contracts\Services\CustomerIo;

interface HasCustomerData
{
    public function getCustomerData(array $data, ?Customer $existing = null): ?array;
}
