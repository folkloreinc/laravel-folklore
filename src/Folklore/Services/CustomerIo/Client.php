<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Support\Concerns\MakesRequests;
use Folklore\Contracts\Services\CustomerIo;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Folklore\Contracts\Entities\User;
use Folklore\Contracts\Entities\Contact;
use Folklore\Contracts\Entities\Entity;
use Folklore\Contracts\Services\CustomerIo\Customer as CustomerContract;
use Folklore\Contracts\Services\CustomerIo\CustomerIdentifiers;
use Folklore\Contracts\Services\CustomerIo\CustomerObject;
use Folklore\Contracts\Services\CustomerIo\Delivery as DeliveryContract;
use Folklore\Contracts\Services\CustomerIo\DeliveryMessage as DeliveryMessageContract;
use Folklore\Contracts\Services\CustomerIo\Newsletter as NewsletterContract;
use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;
use Folklore\Contracts\Services\CustomerIo\CampaignAction as CampaignActionContract;
use Folklore\Contracts\Services\CustomerIo\TransactionalMessage as TransactionalMessageContract;
use Folklore\Contracts\Services\CustomerIo\Campaign as CampaignContract;
use Folklore\Contracts\Services\CustomerIo\HasCustomerData;
use Folklore\Contracts\Services\CustomerIo\HasIdentifier;
use Folklore\Contracts\Services\CustomerIo\HasSubscriptionPreferences;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Folklore\Contracts\Services\CustomerIo\CustomerObject as CustomerObjectContract;

class Client implements CustomerIo
{
    use MakesRequests;

    protected string $apiBaseUrl = 'https://api.customer.io';

    protected string $trackBaseUrl = 'https://track.customer.io';

    public function __construct(
        protected string $key,
        protected string $siteId,
        protected ?string $trackingKey = null,
        ?string $apiBaseUrl = null,
        ?string $trackBaseUrl = null,
        protected bool $debug = false
    ) {
        if (!empty($apiBaseUrl)) {
            $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        }
        if (!empty($trackBaseUrl)) {
            $this->trackBaseUrl = rtrim($trackBaseUrl, '/');
        }
    }

    public function findCustomerFromUser($user): ?CustomerContract
    {
        $email = $user instanceof User || $user instanceof Contact ? $user->email() : null;
        $customer = !empty($email) ? $this->findCustomerByEmail($email) : null;
        if (isset($customer)) {
            return $customer;
        }

        $customer = $this->findCustomerByIdentifier($user);
        if (isset($customer)) {
            return $customer;
        }

        $phone = $user instanceof Contact ? $user->phone() : null;
        $customer = !empty($phone) ? $this->findCustomerByPhone($phone) : null;
        $customerEmail = isset($customer) ? $customer->email() : null;
        if (isset($customer) && (empty($customerEmail) || $customerEmail === $email)) {
            return $customer;
        }

        return null;
    }

    public function findCustomerByIdentifier($identifier): ?CustomerContract
    {
        $identifiers = $this->getIdentifiersFromItem($identifier);
        if (!isset($identifiers)) {
            return null;
        }
        return $this->findCustomerById(array_values($identifiers)[0], array_keys($identifiers)[0]);
    }

    public function findCustomerById(string $id, string $type = 'cio_id'): ?CustomerContract
    {
        $response = $this->requestJson(sprintf('/v1/customers/%s/attributes', $id), 'GET', [
            'id_type' => $type,
        ]);
        $data = data_get($response, 'customer');
        return isset($data) ? new Customer($data) : null;
    }

    public function findCustomerByEmail(string $email): ?CustomerContract
    {
        $response = $this->requestJson('/v1/customers', 'GET', [
            'email' => $email,
        ]);
        $id = data_get($response, 'results.0.cio_id');
        return !is_null($id) ? $this->findCustomerById($id) : null;
    }

    public function findCustomerByPhone(string $phone): ?CustomerContract
    {
        $response = $this->requestJson('/v1/customers', 'POST', [
            'filter' => [
                'and' => [
                    [
                        'attribute' => [
                            'field' => 'phone',
                            'operator' => 'eq',
                            'value' => $phone,
                        ],
                    ],
                ],
            ],
        ]);
        $id = data_get($response, 'identifiers.0.cio_id');
        return !is_null($id) ? $this->findCustomerById($id) : null;
    }

    public function findDeliveryById(string $id): ?DeliveryContract
    {
        $response = $this->requestJson(sprintf('/v1/messages/%s', $id), 'GET');
        $data = data_get($response, 'message');
        return isset($data) ? new Delivery($data, $this) : null;
    }

    public function findDeliveryMessageById(string $id): ?DeliveryMessageContract
    {
        $response = $this->requestJson(sprintf('/v1/messages/%s/archived_message', $id), 'GET');
        $data = data_get($response, 'archived_message');
        return isset($data) ? new DeliveryMessage($data, $this) : null;
    }

    public function getDeliveriesForCustomer(
        $customer,
        $query = [],
        $count = 100,
        $cursor = null
    ): CollectionWithCursor {
        $identifiers = $this->getIdentifiersFromItem($customer);
        if (is_null($identifiers)) {
            return collect();
        }
        $response = $this->requestJson(
            sprintf('/v1/customers/%s/messages', array_values($identifiers)[0]),
            'GET',
            array_merge(!empty($cursor) ? ['start' => $cursor] : [], $query, [
                'id_type' => array_keys($identifiers)[0],
                'limit' => $count,
            ])
        );
        $data = data_get($response, 'messages', []);
        $next = data_get($response, 'next');
        return (new CollectionWithCursor($data))->setCursor($next)->map(function ($item) {
            return new Delivery($item, $this);
        });
    }

    public function findNewsletterById(string $id): ?NewsletterContract
    {
        $response = $this->requestJson(sprintf('/v1/newsletters/%s', $id), 'GET');
        $data = data_get($response, 'newsletter');
        return isset($data) ? new Newsletter($data, $this) : null;
    }

    public function findNewsletterContentById(
        string $newsletterId,
        string $contentId
    ): ?NewsletterContentContract {
        $response = $this->requestJson(
            sprintf('/v1/newsletters/%s/contents/%s', $newsletterId, $contentId),
            'GET'
        );
        $data = data_get($response, 'content');
        return isset($data) ? new NewsletterContent($data) : null;
    }

    public function getNewsletters($query = [], $count = 100, $cursor = null): CollectionWithCursor
    {
        $response = $this->requestJson(
            '/v1/newsletters',
            'GET',
            array_merge(!empty($cursor) ? ['start' => $cursor] : [], $query, [
                'limit' => $count,
            ])
        );
        $data = data_get($response, 'newsletters', []);
        $next = data_get($response, 'next');
        return (new CollectionWithCursor($data))
            ->map(function ($item) {
                return new Newsletter($item, $this);
            })
            ->setCursor($next);
    }

    public function findCampaignById(string $id): ?CampaignContract
    {
        $response = $this->requestJson(sprintf('/v1/campaigns/%s', $id), 'GET');
        $data = data_get($response, 'campaign');
        return isset($data) ? new Campaign($data) : null;
    }

    public function findCampaignActionById(
        string $campaignId,
        string $actionId
    ): ?CampaignActionContract {
        $response = $this->requestJson(
            sprintf('/v1/campaigns/%s/actions/%s', $campaignId, $actionId),
            'GET'
        );
        $data = data_get($response, 'action');
        return isset($data) ? new CampaignAction($data) : null;
    }

    public function findTransactionalMessageById(string $id): ?TransactionalMessageContract
    {
        $response = $this->requestJson(sprintf('/v1/transactional/%s', $id), 'GET');
        $data = data_get($response, 'message');
        return isset($data) ? new TransactionalMessage($data) : null;
    }

    public function findTransactionalMessageByName(string $name): ?TransactionalMessageContract
    {
        $slug = Str::slug($name);
        return $this->getTransactionalMessages()->first(function ($item) use ($slug) {
            return Str::slug($item->name()) == $slug;
        });
    }

    public function createOrUpdateCustomerFromUser(
        $user,
        $extraData = [],
        bool $updateOnly = false
    ): bool {
        $customer = $this->findCustomerFromUser($user);
        $userData = $this->getCustomerDataFromItem($user, $customer);
        $identifier = isset($customer)
            ? 'cio_' . $customer->id()
            : $this->getIdentifierFromItem($user);
        return $this->updateCustomer($identifier, array_merge($userData, $extraData));
    }

    public function updateCustomer($identifier, $data = []): bool
    {
        $identifiers = $this->getIdentifiersFromItem($identifier);
        $response = isset($identifiers)
            ? $this->trackEntity([
                'type' => 'person',
                'action' => 'identify',
                'identifiers' => $identifiers,
                'attributes' => $data,
            ])
            : null;
        return !is_null($response);
    }

    public function deleteCustomer($identifier): bool
    {
        $identifiers = $this->getIdentifiersFromItem($identifier);
        $response = isset($identifiers)
            ? $this->trackEntity([
                'type' => 'person',
                'action' => 'delete',
                'identifiers' => $identifiers,
            ])
            : null;
        return !is_null($response);
    }

    public function deleteCustomerFromUser($user): bool
    {
        $customer = $this->findCustomerFromUser($user);
        $identifier = isset($customer)
            ? 'cio_' . $customer->id()
            : $this->getIdentifierFromItem($user);
        return $this->deleteCustomer($identifier);
    }

    public function mergeCustomers(
        CustomerContract $customer,
        CustomerContract $mergeCustomer
    ): ?CustomerContract {
        $this->trackEntity([
            'type' => 'person',
            'action' => 'merge',
            'primary' => [
                'cio_id' => $customer->id(),
            ],
            'secondary' => [
                'cio_id' => $mergeCustomer->id(),
            ],
        ]);
        return $this->findCustomerById($customer->id());
    }

    public function mergeUsers($user, $mergeUser): ?CustomerContract
    {
        $customer = $this->findCustomerFromUser($user);
        $mergeCustomer = $this->findCustomerFromUser($mergeUser);
        if (!isset($customer) || !isset($mergeCustomer)) {
            return $customer;
        }
        return $this->mergeCustomers($customer, $mergeCustomer);
    }

    public function subscribeToTopic(string $email, $topic, $data = []): bool
    {
        $customer = $this->findCustomerByEmail($email);
        $userData = array_merge(
            [
                'cio_subscription_preferences.topics.' . $topic => true,
            ],
            $data
        );
        $identifier = $email;
        if (isset($customer)) {
            $identifier = 'cio_' . $customer->id();
        }
        return $this->updateCustomer($identifier, $userData);
    }

    public function unsubscribeToTopic(string $email, $topic, $data = []): bool
    {
        $customer = $this->findCustomerByEmail($email);
        $userData = array_merge(
            [
                'cio_subscription_preferences.topics.' . $topic => false,
            ],
            $data
        );
        $identifier = $email;
        if (isset($customer)) {
            $identifier = 'cio_' . $customer->id();
        }
        return $this->updateCustomer($identifier, $userData);
    }

    public function getCustomerDataFromItem($item, ?CustomerContract $customer = null): array
    {
        $data = [];
        if ($item instanceof Entity) {
            $data['id'] = $item->id();
        }
        if ($item instanceof User) {
            $data['name'] = $item->name();
            $data['email'] = $item->email();
        }
        if ($item instanceof Contact) {
            $data['name'] = $item->name();
            $data['firstname'] = $item->firstName();
            $data['lastname'] = $item->lastName();
            $data['phone'] = $item->phone();
            $data['email'] = $item->email();
            $birthdate = $item->birthdate();
            if (isset($birthdate)) {
                $data['birthdate'] = $birthdate->getTimestamp();
            }
        }
        if ($item instanceof HasLocalePreference) {
            $data['locale'] = $item->preferredLocale();
        }
        if ($item instanceof HasSubscriptionPreferences) {
            $data =
                $item instanceof HasSubscriptionPreferences
                    ? $item
                        ->subscriptionPreferences()
                        ->reduce(function ($currentData, $preference) {
                            $currentData[
                                'cio_subscription_preferences.topics.' . $preference->topic()
                            ] = $preference->subscribed();
                            return $currentData;
                        }, $data)
                    : $data;
        }
        if ($item instanceof HasCustomerData) {
            return $item->getCustomerData($data, $customer);
        }
        return $data;
    }

    protected function getIdentifierFromItem($item)
    {
        $identifiers = $this->getIdentifiersFromItem($item);
        return data_get(
            $identifiers,
            'cio_id',
            data_get($identifiers, 'email', data_get($identifiers, 'id', null))
        );
    }

    public function getIdentifiersFromItem($item)
    {
        if (is_array($item)) {
            return $item;
        }

        if (is_string($item)) {
            return $this->getIdentifiersFromIdentifier($item);
        }

        $identifiers = $this->getIdentifiersFromIdentifier(
            $item instanceof HasIdentifier ? $item->customerIoIdentifier() : null
        );
        if (isset($identifiers)) {
            return $identifiers;
        }

        $cioId = $item instanceof CustomerIdentifiers ? $item->cioId() : null;
        if (!empty($cioId)) {
            return [
                'cio_id' => $cioId,
            ];
        }
        $email =
            $item instanceof Contact ||
            $item instanceof User ||
            $item instanceof CustomerIdentifiers
                ? $item->email()
                : null;
        if (!empty($email)) {
            return [
                'email' => $email,
            ];
        }
        $id = $item instanceof Entity || $item instanceof CustomerIdentifiers ? $item->id() : null;
        if (!empty($id)) {
            return [
                'id' => $id,
            ];
        }
        return null;
    }

    protected function getIdentifiersFromIdentifier($identifier): ?array
    {
        if (empty($identifier)) {
            return null;
        }
        if (preg_match('/^cio_(.*)$/', $identifier, $matches) === 1) {
            return [
                'cio_id' => $matches[1],
            ];
        }
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $identifier,
            ];
        }
        return is_numeric($identifier)
            ? [
                'id' => $identifier,
            ]
            : null;
    }

    public function getTransactionalMessages(): Collection
    {
        $response = $this->requestJson('/v1/transactional', 'GET');
        return collect(data_get($response, 'messages', []))->map(function ($item) {
            return new TransactionalMessage($item);
        });
    }

    public function sendEmail($message, string $to)
    {
        $data = $message instanceof Arrayable ? $message->toArray() : $message;
        if (!isset($data['to'])) {
            $data['to'] = $to;
        }
        $data['identifiers'] = [
            'email' => $to,
        ];
        $response = $this->requestJson('/v1/send/email', 'POST', $data);
        return $response;
    }

    public function triggerWebhook(string $url, array $data)
    {
        $response = $this->requestJson($url, 'POST', $data);
        return $response;
    }

    public function identifyObject(CustomerObject $object)
    {
        $relationships = collect($object->relationships() ?? [])
            ->map(function ($relationship) {
                return [
                    'identifiers' => $this->getIdentifiersFromItem($relationship),
                ];
            })
            ->filter(function ($relationship) {
                return !is_null($relationship);
            })
            ->values();

        $request = [
            'identifiers' => [
                'object_type_id' => (string) $object->type(),
                'object_id' => $object->id(),
            ],
            'type' => 'object',
            'action' => 'identify',
            'attributes' => $object->attributes() ?? [],
        ];

        if ($relationships->isNotEmpty()) {
            $request['cio_relationships'] = $relationships->toArray();
        }

        $response = $this->trackEntity($request);
        return $response;
    }

    public function addRelationshipsToObject($typeId, $objectId, Collection $relationships)
    {
        $relationships = $relationships
            ->map(function ($relationship) {
                return is_array($relationship)
                    ? $relationship
                    : [
                        'identifiers' => $this->getIdentifiersFromItem($relationship),
                    ];
            })
            ->filter(function ($relationship) {
                return !is_null($relationship);
            })
            ->values()
            ->toArray();

        $request = [
            'identifiers' => [
                'object_type_id' => (string) $typeId,
                'object_id' => $objectId,
            ],
            'type' => 'object',
            'action' => 'add_relationships',
            'cio_relationships' => $relationships,
        ];

        $response = $this->trackEntity($request);
        return $response;
    }

    public function findObjectById($typeId, $objectId): ?CustomerObjectContract
    {
        $response = $this->requestJson(
            sprintf('/v1/objects/%s/%s/attributes', $typeId, $objectId),
            'GET'
        );
        $data = data_get($response, 'object');
        return isset($data) ? new CustomerObject($data) : null;
    }

    public function trackUserPageview($user, string $url, $data): bool
    {
        $identifier = $this->getIdentifierFromItem($user);
        return $this->trackCustomerEventBase($identifier, 'page', $url, $data) !== null;
    }

    public function trackUserEvent($user, string $name, $data): bool
    {
        $identifier = $this->getIdentifierFromItem($user);
        return $this->trackCustomerEventBase($identifier, 'event', $name, $data) !== null;
    }

    public function trackAnonymousPageview(string $anonymousId, string $url, $data): bool
    {
        return $this->trackAnonymousEventBase($anonymousId, 'page', $url, $data) !== null;
    }

    public function trackAnonymousEvent(string $anonymousId, string $name, $data): bool
    {
        return $this->trackAnonymousEventBase($anonymousId, 'event', $name, $data) !== null;
    }

    protected function trackCustomerEventBase($identifier, $action, $name, $data): ?array
    {
        $identifiers = $this->getIdentifiersFromIdentifier($identifier);
        return $this->trackEntity(
            array_merge(
                [
                    'type' => 'person',
                    'action' => $action,
                    'identifiers' => $identifiers,
                    'name' => $name,
                    'attributes' => Arr::except($data, ['timestamp', 'id']),
                ],
                Arr::only($data, ['timestamp', 'id'])
            )
        );
    }

    protected function trackAnonymousEventBase($anonymousId, $type, $name, $data): ?array
    {
        return $this->requestJson(
            '/api/v1/events',
            'POST',
            array_merge(
                [
                    'type' => $type,
                    'name' => $name,
                    'anonymous_id' => $anonymousId,
                    'attributes' => Arr::except($data, ['timestamp', 'id']),
                ],
                Arr::only($data, ['timestamp', 'id'])
            ),
            [
                'base_uri' => $this->trackBaseUrl,
            ]
        );
    }

    protected function trackEntity($entity): ?array
    {
        return $this->requestJson('/api/v2/entity', 'POST', $entity, [
            'base_uri' => $this->trackBaseUrl,
        ]);
    }

    protected function getAuthorizationHeader($url)
    {
        if (in_array($url, ['/api/v2/entity', '/api/v1/events'])) {
            return sprintf('Basic %s', base64_encode($this->siteId . ':' . $this->trackingKey));
        }
        return sprintf('Bearer %s', $this->key);
    }

    protected function getRequestBaseUri()
    {
        return $this->apiBaseUrl;
    }

    protected function getRequestLogErrors()
    {
        return $this->debug;
    }
}
