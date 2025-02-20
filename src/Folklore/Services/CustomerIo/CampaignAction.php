<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\CampaignAction as CampaignActionContract;

class CampaignAction extends MessageContent implements CampaignActionContract
{
    public function id(): string
    {
        return data_get($this->data, 'id');
    }
}
