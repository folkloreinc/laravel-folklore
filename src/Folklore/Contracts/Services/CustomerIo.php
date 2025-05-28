<?php

namespace Folklore\Contracts\Services;

use Folklore\Contracts\Entities\Contact;
use Illuminate\Support\Collection;
use Folklore\Contracts\Entities\User;
use Folklore\Contracts\Services\CustomerIo\Campaign;
use Folklore\Contracts\Services\CustomerIo\CampaignAction;
use Folklore\Contracts\Services\CustomerIo\Customer;
use Folklore\Contracts\Services\CustomerIo\CustomerObject;
use Folklore\Contracts\Services\CustomerIo\Delivery;
use Folklore\Contracts\Services\CustomerIo\DeliveryMessage;
use Folklore\Contracts\Services\CustomerIo\Newsletter;
use Folklore\Contracts\Services\CustomerIo\NewsletterContent;
use Folklore\Contracts\Services\CustomerIo\TransactionalMessage;
use Folklore\Services\CustomerIo\CollectionWithCursor;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface CustomerIo
{
    public function findCustomerById(string $id, string $type = 'cio_id'): ?Customer;

    public function findCustomerByIdentifier($identifier): ?Customer;

    public function findCustomerByEmail(string $email): ?Customer;

    public function findCustomerByPhone(string $phone): ?Customer;

    public function findCustomerFromUser($user): ?Customer;

    public function findNewsletterById(string $id): ?Newsletter;

    public function findNewsletterContentById(
        string $newsletterId,
        string $contentId
    ): ?NewsletterContent;

    public function getNewsletters($query = [], $count = 50, $start = null): CollectionWithCursor;

    public function findCampaignById(string $id): ?Campaign;

    public function findCampaignActionById(string $campaignId, string $actionId): ?CampaignAction;

    public function findTransactionalMessageById(string $id): ?TransactionalMessage;

    public function findTransactionalMessageByName(string $name): ?TransactionalMessage;

    public function getTransactionalMessages(): Collection;

    public function findDeliveryById(string $id): ?Delivery;

    public function findDeliveryMessageById(string $id): ?DeliveryMessage;

    public function getDeliveriesForCustomer($identifier, $query = [], $count = 50, $start = null): CollectionWithCursor;

    public function sendEmail($message, string $to);

    public function triggerWebhook(string $url, array $data);

    public function identifyObject(CustomerObject $object);

    public function findObjectById($typeId, $objectId): ?CustomerObject;

    public function addRelationshipsToObject($typeId, $objectId, Collection $relationships);

    public function createOrUpdateCustomerFromUser(
        $user,
        $extraData = [],
        bool $updateOnly = false
    ): bool;

    public function subscribeToTopic(string $email, $topic, $data = []): bool;

    public function unsubscribeToTopic(string $email, $topic, $data = []): bool;

    public function updateCustomer($identifier, $data = []): bool;

    public function mergeCustomers(Customer $customer, Customer $mergeCustomer): ?Customer;

    public function mergeUsers($user, $mergeUser): ?Customer;

    public function deleteCustomer($identifier): bool;

    public function deleteCustomerFromUser($user): bool;

    public function trackUserPageview($user, string $url, $data): bool;

    public function trackUserEvent($user, string $name, $data): bool;

    public function trackAnonymousPageview(string $anonymousId, string $url, $data): bool;

    public function trackAnonymousEvent(string $anonymousId, string $name, $data): bool;

    public function getIdentifiersFromItem($item);
}
