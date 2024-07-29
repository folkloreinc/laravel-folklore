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

    public function __construct($key, $siteId, $trackingKey = null)
    {
        $this->key = $key;
        $this->siteId = $siteId;
        $this->trackingKey = $trackingKey;
    }

    public function findCustomerFromUser($user): ?CustomerContract
    {
        $email = $user instanceof User || $user instanceof Contact ? $user->email() : null;
        $customer = !empty($email) ? $this->findCustomerByEmail($email) : null;
        if (isset($customer)) {
            return $customer;
        }

        $identifier = $user instanceof HasIdentifier ? $user->customerIoIdentifier() : null;
        if (!empty($identifier) && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $customer = $this->findCustomerById($identifier, 'email');
        } elseif (!empty($identifier) && preg_match('/^cio_(.*)$/', $identifier, $matches) === 1) {
            $customer = $this->findCustomerById($matches[1], 'cio_id');
        } elseif (!empty($identifier)) {
            $customer = $this->findCustomerById($identifier, 'id');
        }
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

    public function findCustomerById(string $id, string $type = 'cio_id'): ?CustomerContract
    {
        $response = $this->requestJson(
            sprintf('https://api.customer.io/v1/customers/%s/attributes', $id),
            'GET',
            [
                'id_type' => $type,
            ]
        );
        $data = data_get($response, 'customer');
        return isset($data) ? new Customer($data) : null;
    }

    public function findCustomerByEmail(string $email): ?CustomerContract
    {
        $response = $this->requestJson('https://api.customer.io/v1/customers', 'GET', [
            'email' => $email,
        ]);
        $id = data_get($response, 'results.0.cio_id');
        return !is_null($id) ? $this->findCustomerById($id) : null;
    }

    public function findCustomerByPhone(string $phone): ?CustomerContract
    {
        $response = $this->requestJson('https://api.customer.io/v1/customers', 'POST', [
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
        $response = $this->requestJson(
            sprintf('https://api.customer.io/v1/messages/%s', $id),
            'GET'
        );
        $data = data_get($response, 'message');
        return isset($data) ? new Delivery($data, $this) : null;
    }

    public function findNewsletterById(string $id): ?NewsletterContract
    {
        $response = $this->requestJson(
            sprintf('https://api.customer.io/v1/newsletters/%s', $id),
            'GET'
        );
        $data = data_get($response, 'newsletter');
        return isset($data) ? new Newsletter($data, $this) : null;
    }

    public function findNewsletterContentById(
        string $newsletterId,
        string $contentId
    ): ?NewsletterContentContract {
        $response = $this->requestJson(
            sprintf(
                'https://api.customer.io/v1/newsletters/%s/contents/%s',
                $newsletterId,
                $contentId
            ),
            'GET'
        );
        $data = data_get($response, 'content');
        return isset($data) ? new NewsletterContent($data) : null;
    }

    public function findTransactionalMessageById(string $id): ?TransactionalMessageContract
    {
        $response = $this->requestJson(
            sprintf('https://api.customer.io/v1/transactional/%s', $id),
            'GET'
        );
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
        return $this->updateCustomer(
            $identifier,
            array_merge(
                $userData,
                $extraData,
                $updateOnly
                    ? [
                        '_update' => true,
                    ]
                    : []
            )
        );
    }

    public function updateCustomer(string $identifier, $data = []): bool
    {
        $response = $this->requestJson(
            'https://track.customer.io/api/v1/customers/' . $identifier,
            'PUT',
            $data
        );
        return !is_null($response);
    }

    public function deleteCustomer(string $identifier): bool
    {
        $response = $this->requestJson(
            'https://track.customer.io/api/v1/customers/' . $identifier,
            'DELETE'
        );
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
        $response = $this->requestJson('https://track.customer.io/api/v1/merge_customers', 'POST', [
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

    public function subscribeToTopic(string $email, $topic): bool
    {
        $customer = $this->findCustomerByEmail($email);
        $userData = [
            'cio_subscription_preferences.topics.' . $topic => true,
        ];
        $identifier = $email;
        if (isset($customer)) {
            $identifier = 'cio_' . $customer->id();
        }
        return $this->updateCustomer($identifier, $userData);
    }

    public function unsubscribeToTopic(string $email, $topic): bool
    {
        $customer = $this->findCustomerByEmail($email);
        $userData = [
            'cio_subscription_preferences.topics.' . $topic => false,
        ];
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
                            data_set(
                                $currentData,
                                'cio_subscription_preferences.topics.' . $preference->topic(),
                                $preference->subscribed()
                            );
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

    protected function getIdentifiersFromResource($resource)
    {
        $cioId =
            ($resource instanceof HasIdentifier ? $resource->customerIoIdentifier() : null) ??
            ($resource instanceof CustomerIdentifiers ? $resource->cioId() : null);
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

    public function getTransactionalMessages(): Collection
    {
        $response = $this->requestJson('https://api.customer.io/v1/transactional', 'GET');
        return collect(data_get($response, 'messages', []))->map(function ($item) {
            return new TransactionalMessage($item);
        });
    }

    public function sendEmail($message, string $to)
    {
        $data = $message instanceof Arrayable ? $message->toArray() : $message;
        $data['to'] = $to;
        $response = $this->requestJson('https://api.customer.io/v1/send/email', 'POST', $data);
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
                'object_type_id' => $object->type(),
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
                return [
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
                'object_type_id' => $typeId,
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
            sprintf('https://api.customer.io/v1/objects/%s/%s/attributes', $typeId, $objectId),
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

    protected function trackCustomerEventBase($identifier, $type, $name, $data): ?array
    {
        return $this->requestJson(
            sprintf('https://track.customer.io/api/v1/customers/%s/events', $identifier),
            'POST',
            array_merge(
                [
                    'type' => $type,
                    'name' => $name,
                    'data' => Arr::except($data, ['timestamp', 'id']),
                ],
                Arr::only($data, ['timestamp', 'id'])
            )
        );
    }

    protected function trackAnonymousEventBase($anonymousId, $type, $name, $data): ?array
    {
        return $this->requestJson(
            'https://track.customer.io/api/v1/events',
            'POST',
            array_merge(
                [
                    'type' => $type,
                    'name' => $name,
                    'anonymous_id' => $anonymousId,
                    'data' => Arr::except($data, ['timestamp', 'id']),
                ],
                Arr::only($data, ['timestamp', 'id'])
            )
        );
    }

    protected function trackEntity($entity): ?array
    {
        return $this->requestJson('https://track.customer.io/api/v2/entity', 'POST', $entity);
    }

    protected function getAuthorizationHeader($url)
    {
        if (preg_match('/^https\:\/\/track\.customer\.io\//', $url) === 1) {
            return sprintf('Basic %s', base64_encode($this->siteId . ':' . $this->trackingKey));
        }
        return sprintf('Bearer %s', $this->key);
    }
}
