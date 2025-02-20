<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\CustomerIdentifiers as CustomerIdentifiersContract;
use Folklore\Contracts\Services\CustomerIo\Delivery as DeliveryContract;
use Folklore\Contracts\Services\CustomerIo\DeliveryMessage as DeliveryMessageContract;
use Folklore\Contracts\Services\CustomerIo\Newsletter as NewsletterContract;
use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;
use Folklore\Contracts\Services\CustomerIo\CampaignAction as CampaignActionContract;
use Folklore\Contracts\Services\CustomerIo\TransactionalMessage as TransactionalMessageContract;
use Folklore\Contracts\Services\CustomerIo\Campaign as CampaignContract;
use Folklore\Contracts\Services\CustomerIo;

class Delivery implements DeliveryContract
{
    protected $data;

    protected $service;

    protected $newsletter;

    protected $message;

    protected $campaign;

    protected $content;

    protected $action;

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
        $subject = data_get($this->data, 'subject');
        if (!empty($subject)) {
            return $subject;
        }
        $message = $this->message();
        if (isset($message)) {
            return $message->subject();
        } elseif ($this->isNewsletter()) {
            return $this->content()->subject();
        } elseif ($this->isCampaign()) {
            return $this->action()->subject();
        }
        return null;
    }

    public function body(): ?string
    {
        $message = $this->message();
        if (isset($message)) {
            return $message->body();
        } elseif ($this->isNewsletter()) {
            return $this->content()->body();
        } elseif ($this->isCampaign()) {
            return $this->action()->body();
        }
        return null;
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

    public function message(): ?DeliveryMessageContract
    {
        if (!isset($this->message)) {
            $this->message = $this->service->findDeliveryMessageById($this->id());
        }
        return $this->message;
    }

    public function campaign(): ?CampaignContract
    {
        $id = data_get($this->data, 'campaign_id');
        if (!empty($id) && !isset($this->campaign)) {
            $this->campaign = $this->service->findCampaignById($id);
        }
        return $this->campaign;
    }

    public function transactionalMessage(): ?TransactionalMessageContract
    {
        $id = data_get($this->data, 'transactional_message_id');
        if (!empty($id) && !isset($this->transactional)) {
            $this->transactional = $this->service->findTransactionalMessageById($id);
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
            $this->content = $this->service->findNewsletterContentById($newsletterId, $contentId);
        }
        return $this->content;
    }

    public function action(): ?CampaignActionContract
    {
        $campaignId = data_get($this->data, 'campaign_id');
        $actionId = data_get($this->data, 'action_id');
        if (!empty($campaignId) && !empty($actionId) && !isset($this->action)) {
            $this->action = $this->service->findCampaignActionById($campaignId, $actionId);
        }
        return $this->action;
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
