<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\CustomerIdentifiers as CustomerIdentifiersContract;
use Folklore\Contracts\Services\CustomerIo\Delivery as DeliveryContract;
use Folklore\Contracts\Services\CustomerIo\Newsletter as NewsletterContract;
use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;
use Folklore\Contracts\Services\CustomerIo\TransactionalMessage as TransactionalMessageContract;
use Folklore\Contracts\Services\CustomerIo;

class Delivery implements DeliveryContract
{
    protected $data;

    protected $service;

    protected $newsletter;

    protected $content;

    protected $transactional;

    protected $identifiers;

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

    public function subject(): ?string
    {
        return data_get($this->data, 'subject');
    }

    public function isTransactional(): bool
    {
        $id = data_get($this->data, 'transactional_message_id');
        return !empty($id);
    }

    public function isCampaign(): bool
    {
        $id = data_get($this->data, 'campaign_id');
        return !empty($id);
    }

    public function isNewsletter(): bool
    {
        $id = data_get($this->data, 'newsletter_id');
        return !empty($id);
    }

    public function transactionalMessage(): ?TransactionalMessageContract
    {
        $id = data_get($this->data, 'transactional_message_id');
        if (!empty($id) && !isset($this->transactional)) {
            $this->transactional = $this->service->findTransactionMessageById($id);
        }
        return $this->transactional;
    }

    public function newsletter(): ?NewsletterContract
    {
        $id = data_get($this->data, 'newsletter_id');
        if (!empty($id) && !isset($this->newsletter)) {
            $this->newsletter = $this->service->findNewsletterById($id);
        }
        return $this->newsletter;
    }

    public function content(): ?NewsletterContentContract
    {
        $newsletterId = data_get($this->data, 'newsletter_id');
        $contentId = data_get($this->data, 'content_id');
        if (!empty($newsletterId) && !empty($contentId) && !isset($this->content)) {
            $this->content = $this->service->findNewsletterContentById(
                $newsletterId,
                $contentId
            );
        }
        return $this->content;
    }

    public function customerIdentifiers(): CustomerIdentifiersContract
    {
        if (!isset($this->identifiers)) {
            $this->identifiers = new CustomerIdentifiers(
                data_get($this->data, 'customer_identifiers')
            );
        }
        return $this->identifiers;
    }
}
