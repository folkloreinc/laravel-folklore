<?php

namespace Folklore\Notifications;

use Illuminate\Contracts\Support\Arrayable;

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
        return new self('https://api.customer.io/v1/webhook/' . $id);
    }

    public function data(array $data)
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
}
