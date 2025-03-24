<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\DeliveryMessage as DeliveryMessageContract;

class DeliveryMessage extends MessageContent implements DeliveryMessageContract
{
    public function id(): string
    {
        return data_get($this->data, 'id');
    }
}
