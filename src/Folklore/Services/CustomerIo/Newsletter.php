<?php

namespace Folklore\Services\CustomerIo;

use Carbon\Carbon;
use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;
use Folklore\Contracts\Services\CustomerIo\Newsletter as NewsletterContract;
use Folklore\Contracts\Services\CustomerIo;
use Illuminate\Support\Collection;

class Newsletter implements NewsletterContract
{
    protected $data;

    protected $service;

    protected $content;

    public function __construct($data, CustomerIo $service)
    {
        $this->data = $data;
        $this->service = $service;
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

    public function tags(): Collection
    {
        return collect(data_get($this->data, 'tags', []));
    }

    public function content(): NewsletterContentContract
    {
        if (!isset($this->content)) {
            $this->content = $this->service->findNewsletterContentById(
                $this->id(),
                data_get($this->data, 'content_ids.0')
            );
        }
        return $this->content;
    }

    public function sentAt(): ?Carbon
    {
        $timestamp = data_get($this->data, 'sent_at');
        return !empty($timestamp) ? Carbon::createFromTimestampUTC($timestamp) : null;
    }

    public function createdAt(): ?Carbon
    {
        $timestamp = data_get($this->data, 'created');
        return !empty($timestamp) ? Carbon::createFromTimestampUTC($timestamp) : null;
    }

    public function updatedAt(): ?Carbon
    {
        $timestamp = data_get($this->data, 'updated');
        return !empty($timestamp) ? Carbon::createFromTimestampUTC($timestamp) : null;
    }
}
