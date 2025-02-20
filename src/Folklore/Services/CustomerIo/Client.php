<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Support\Concerns\MakesRequests;
use Folklore\Contracts\Services\CustomerIo;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Folklore\Contracts\Resources\User;
use Folklore\Contracts\Resources\Contact;
use Folklore\Contracts\Resources\Resource;
use Folklore\Contracts\Services\CustomerIo\Customer as CustomerContract;
use Folklore\Contracts\Services\CustomerIo\CustomerIdentifiers;
use Folklore\Contracts\Services\CustomerIo\CustomerObject;
use Folklore\Contracts\Services\CustomerIo\Delivery as DeliveryContract;
use Folklore\Contracts\Services\CustomerIo\Newsletter as NewsletterContract;
use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;
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

    protected $key;

    protected $siteId;

    protected $trackingKey;

    protected $apiBaseUrl;

    protected $trackBaseUrl;

    public function __construct(
        $key,
        $siteId,
        $trackingKey = null,
        $apiBaseUrl = null,
        $trackBaseUrl = null
    ) {
        $this->key = $key;
        $this->siteId = $siteId;
        $this->trackingKey = $trackingKey;
        $this->apiBaseUrl = !empty($apiBaseUrl)
            ? rtrim($apiBaseUrl, '/')
            : 'https://api.customer.io';
        $this->trackBaseUrl = !empty($trackBaseUrl)
            ? rtrim($trackBaseUrl, '/')
            : 'https://track.customer.io';
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
        if (isset($customer)) {
            return $customer;
        }

        return null;
    }

    public function findCustomerByIdentifier($identifier): ?CustomerContract
    {
        $identifiers = $this->getIdentifiersFromResource($identifier);
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

    public function findCampaignById(string $id): ?CampaignContract
    {
        $response = $this->requestJson(sprintf('/v1/campaigns/%s', $id), 'GET');
        $data = data_get($response, 'campaign');
        return isset($data) ? new Campaign($data) : null;
    }

    public function findCampaignActionById(
        string $campaignId,
        string $actionId
    ): ?NewsletterContentContract {
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
        $userData = $this->getCustomerDataFromResource($user, $customer);
        $identifier = isset($customer)
            ? 'cio_' . $customer->id()
            : $this->getIdentifierFromResource($user);
        return $this->updateCustomer($identifier, array_merge($userData, $extraData));
    }

    public function updateCustomer($identifier, $data = []): bool
    {
        $identifiers = $this->getIdentifiersFromResource($identifier);
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
        $identifiers = $this->getIdentifiersFromResource($identifier);
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
            : $this->getIdentifierFromResource($user);
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

    public function getCustomerDataFromResource(
        $resource,
        ?CustomerContract $customer = null
    ): array {
        $data = [];
        if ($resource instanceof Resource) {
            $data['id'] = $resource->id();
        }
        if ($resource instanceof User) {
            $data['name'] = $resource->name();
            $data['email'] = $resource->email();
        }
        if ($resource instanceof Contact) {
            $data['name'] = $resource->name();
            $data['firstname'] = $resource->firstName();
            $data['lastname'] = $resource->lastName();
            $data['phone'] = $resource->phone();
            $data['email'] = $resource->email();
            $birthdate = $resource->birthdate();
            if (isset($birthdate)) {
                $data['birthdate'] = $birthdate->getTimestamp();
            }
        }
        if ($resource instanceof HasLocalePreference) {
            $data['locale'] = $resource->preferredLocale();
        }
        if ($resource instanceof HasSubscriptionPreferences) {
            $data =
                $resource instanceof HasSubscriptionPreferences
                    ? $resource
                        ->subscriptionPreferences()
                        ->reduce(function ($currentData, $preference) {
                            $currentData[
                                'cio_subscription_preferences.topics.' . $preference->topic()
                            ] = $preference->subscribed();
                            return $currentData;
                        }, $data)
                    : $data;
        }
        if ($resource instanceof HasCustomerData) {
            return $resource->getCustomerData($data, $customer);
        }
        return $data;
    }

    protected function getIdentifierFromResource($resource)
    {
        $identifiers = $this->getIdentifiersFromResource($resource);
        return data_get(
            $identifiers,
            'cio_id',
            data_get($identifiers, 'email', data_get($identifiers, 'id', null))
        );
    }

    public function getIdentifiersFromResource($resource)
    {
        if (is_array($resource)) {
            return $resource;
        }

        if (is_string($resource)) {
            return $this->getIdentifiersFromIdentifier($resource);
        }

        $identifiers = $this->getIdentifiersFromIdentifier(
            $resource instanceof HasIdentifier ? $resource->customerIoIdentifier() : null
        );
        if (isset($identifiers)) {
            return $identifiers;
        }

        $cioId = $resource instanceof CustomerIdentifiers ? $resource->cioId() : null;
        if (!empty($cioId)) {
            return [
                'cio_id' => $cioId,
            ];
        }
        $email =
            $resource instanceof Contact ||
            $resource instanceof User ||
            $resource instanceof CustomerIdentifiers
                ? $resource->email()
                : null;
        if (!empty($email)) {
            return [
                'email' => $email,
            ];
        }
        $id =
            $resource instanceof Resource || $resource instanceof CustomerIdentifiers
                ? $resource->id()
                : null;
        if (!empty($id)) {
            return [
                'id' => $id,
            ];
        }
        return null;
    }

    protected function getIdentifiersFromIdentifier(string $identifier): ?array
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

    public function getDeliveriesForIdentifier($identifier, $query = [], $count = 50): Collection
    {
        $identifiers = $this->getIdentifiersFromResource($identifier);
        if (is_null($identifiers)) {
            return collect();
        }
        $response = $this->requestJson(
            sprintf('/v1/customers/%s/messages', array_values($identifiers)[0]),
            'GET',
            [
                'id_type' => array_keys($identifiers)[0],
            ]
        );
        $data = data_get($response, 'messages', []);
        return collect($data)->map(function ($item) {
            return new Delivery($item, $this);
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
                    'identifiers' => $this->getIdentifiersFromResource($relationship),
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
                        'identifiers' => $this->getIdentifiersFromResource($relationship),
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
        $identifier = $this->getIdentifierFromResource($user);
        return $this->trackCustomerEventBase($identifier, 'page', $url, $data) !== null;
    }

    public function trackUserEvent($user, string $name, $data): bool
    {
        $identifier = $this->getIdentifierFromResource($user);
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
}
