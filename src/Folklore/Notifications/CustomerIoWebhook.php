<?php

namespace Folklore\Notifications;

class CustomerIoWebhook
{
    public $url;

    public $data = [];

    public function __construct($url = null)
    {
        $this->url = $url;
    }

    public static function fromId($id)
    {
        $baseUrl = config('services.customerio.api_base_url') ?: 'https://api.customer.io';

        return new self(rtrim($baseUrl, '/').'/v1/webhook/'.$id);
    }

    public function data(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }
}
