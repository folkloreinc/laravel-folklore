<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;
use Folklore\Contracts\Services\CustomerIo\Newsletter as NewsletterContract;
use Folklore\Contracts\Services\CustomerIo\Service;

class Newsletter implements NewsletterContract
{
    protected $data;

    protected $content;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function id(): string
    {
        return data_get($this->data, 'id');
    }

    public function type(): string
    {
        return data_get($this->data, 'type');
    }

    public function medium(): string
    {
        if ($this->type() === 'twilio') {
            return 'sms';
        }
        return 'email';
    }

    public function name(): string
    {
        return data_get($this->data, 'name');
    }

    public function content(): NewsletterContentContract
    {
        if (!isset($this->content)) {
            $this->content = resolve(Service::class)->findNewsletterContentById(
                $this->id(),
                data_get($this->data, 'content_ids.0')
            );
        }
        return $this->content;
    }
}
