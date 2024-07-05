<?php

namespace Folklore\Contracts\Services;

use Folklore\Contracts\Resources\Contact;
use Illuminate\Support\Collection;
use Folklore\Contracts\Resources\User;
use Folklore\Contracts\Services\CustomerIo\Customer;
use Folklore\Contracts\Services\CustomerIo\Delivery;
use Folklore\Contracts\Services\CustomerIo\Newsletter;
use Folklore\Contracts\Services\CustomerIo\NewsletterContent;
use Folklore\Contracts\Services\CustomerIo\TransactionalMessage;

interface CustomerIo
{
    public function findCustomerById(string $id, string $type = 'cio_id'): ?Customer;

    public function findCustomerByEmail(string $email): ?Customer;

    public function findCustomerByPhone(string $phone): ?Customer;

    public function findCustomerFromUser($user): ?Customer;

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

    public function triggerWebhook(string $url, array $data);

    public function createOrUpdateCustomerFromUser(
        $user,
        $extraData = [],
        bool $updateOnly = false
    ): bool;

    public function subscribeToTopic(string $email, $topic): bool;

    public function unsubscribeToTopic(string $email, $topic): bool;

    public function updateCustomer(string $identifier, $data = []): bool;

    public function mergeCustomers(Customer $customer, Customer $mergeCustomer): ?Customer;

    public function mergeUsers($user, $mergeUser): ?Customer;

    public function deleteCustomer(string $identifier): bool;

    public function deleteCustomerFromUser($user): bool;

    public function trackUserPageview($user, string $url, $data): bool;

    public function trackUserEvent($user, string $name, $data): bool;

    public function trackAnonymousPageview(string $anonymousId, string $url, $data): bool;

    public function trackAnonymousEvent(string $anonymousId, string $name, $data): bool;
}
