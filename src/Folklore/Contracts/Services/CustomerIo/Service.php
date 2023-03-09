<?php

namespace Folklore\Contracts\Services\CustomerIo;

use Illuminate\Support\Collection;
use Folklore\Contracts\Resources\User;

interface Service
{
    public function findCustomerById(string $id): ?Customer;

    public function findCustomerByEmail(string $email): ?Customer;

    public function findCustomerByPhone(string $phone): ?Customer;

    public function findCustomerFromUser(User $user): ?Customer;

    public function findDeliveryById(string $id): ?Delivery;

    public function findNewsletterById(string $id): ?Newsletter;

    public function findNewsletterContentById(
        string $newsletterId,
        string $contentId
    ): ?NewsletterContent;

    public function findTransactionalMessageById(string $id): ?TransactionalMessage;

    public function findTransactionalMessageByName(string $name): ?TransactionalMessage;

    public function getTransactionalMessages(): Collection;

    public function sendEmail($message, string $to);

    public function createOrUpdateCustomerFromUser(
        User $user,
        $extraData = [],
        bool $updateOnly = false
    ): bool;

    public function updateCustomer(string $identifier, $data = []): bool;

    public function trackUserPageview(User $user, string $url, $data): bool;

    public function trackUserEvent(User $user, string $name, $data): bool;

    public function trackAnonymousPageview(string $anonymousId, string $url, $data): bool;

    public function trackAnonymousEvent(string $anonymousId, string $name, $data): bool;
}
