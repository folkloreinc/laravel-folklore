<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Folklore\Contracts\Resources\Resource;

interface Delivery extends Resource
{
    public function type(): string;

    public function medium(): string;

    public function subject(): ?string;

    public function body(): ?string;

    public function isTransactional(): bool;

    public function isCampaign(): bool;

    public function isNewsletter(): bool;

    public function message(): ?DeliveryMessage;

    public function transactionalMessage(): ?TransactionalMessage;

    public function campaign(): ?Campaign;

    public function newsletter(): ?Newsletter;

    public function action(): ?CampaignAction;

    public function content(): ?NewsletterContent;

    public function customerIdentifiers(): CustomerIdentifiers;
}
