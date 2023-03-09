<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Support\Concerns\MakesRequests;
use Folklore\Contracts\Services\CustomerIo\Service as CustomerIo;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Folklore\Contracts\Resources\User;
use Folklore\Contracts\Resources\Contact;
use Folklore\Contracts\Services\CustomerIo\Customer as CustomerContract;
use Folklore\Contracts\Services\CustomerIo\Delivery as DeliveryContract;
use Folklore\Contracts\Services\CustomerIo\Newsletter as NewsletterContract;
use Folklore\Contracts\Services\CustomerIo\NewsletterContent as NewsletterContentContract;
use Folklore\Contracts\Services\CustomerIo\TransactionalMessage as TransactionalMessageContract;
use Folklore\Contracts\Services\CustomerIo\HasCustomerData;
use Folklore\Contracts\Services\CustomerIo\HasSubscriptionPreferences;
use Illuminate\Contracts\Translation\HasLocalePreference;

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

    public function findCustomerFromUser(User $user): ?CustomerContract
    {
        $email = $user->email();
        $phone = $user instanceof Contact ? $user->phone() : null;
        $customer = !empty($email) ? $this->findCustomerByEmail($email) : null;
        if (is_null($customer) && !empty($phone)) {
            $customer = $this->findCustomerByPhone($phone);
        }
        return $customer;
    }

    public function findCustomerById(string $id): ?CustomerContract
    {
        $response = $this->requestJson(
            sprintf('https://api.customer.io/v1/customers/%s/attributes', $id),
            'GET'
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
        return isset($data) ? new Delivery($data) : null;
    }

    public function findNewsletterById(string $id): ?NewsletterContract
    {
        $response = $this->requestJson(
            sprintf('https://api.customer.io/v1/newsletters/%s', $id),
            'GET'
        );
        $data = data_get($response, 'newsletter');
        return isset($data) ? new Newsletter($data) : null;
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
        User $user,
        $extraData = [],
        bool $updateOnly = false
    ): bool {
        $customer = $this->findCustomerFromUser($user);
        $userData = $this->getCustomerDataFromUser($user, $customer);
        $identifier = $this->getIdentifierFromUser($user, $customer);
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

    public function getCustomerDataFromUser(User $user, ?CustomerContract $customer = null): array
    {
        if ($user instanceof HasCustomerData) {
            return $user->toCustomerData($customer);
        }
        $data = [
            'id' => $user->id(),
            'email' => $user->email(),
        ];
        if ($user instanceof Contact) {
            $data['firstname'] = $user->firstName();
            $data['lastname'] = $user->lastName();
            $data['phone'] = $user->phone();
        }
        if ($user instanceof HasLocalePreference) {
            $data['locale'] = $user->preferredLocale();
        }
        if (isset($customer) && $user instanceof HasSubscriptionPreferences) {
            $data['cio_subscription_preferences'] = [
                'topics' => array_merge(
                    isset($customer)
                        ? $customer
                            ->subscriptionPreferences()
                            ->mapWithKeys(function ($preference) {
                                return [
                                    $preference->topic() => $preference->subscribed(),
                                ];
                            })
                            ->toArray()
                        : [],
                    $user instanceof HasSubscriptionPreferences
                        ? $user
                            ->subscriptionPreferences()
                            ->mapWithKeys(function ($preference) {
                                return [
                                    $preference->topic() => $preference->subscribed(),
                                ];
                            })
                            ->toArray()
                        : []
                ),
            ];
        }
        return $data;
    }

    protected function getIdentifierFromUser(User $user, ?CustomerContract $customer = null)
    {
        $identifier = $user->email();
        if (isset($customer)) {
            $identifier = 'cio_' . $customer->id();
        }
        return $identifier;
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

    public function trackUserPageview(User $user, string $url, $data): bool
    {
        $email = $user->email();
        $identifier = !empty($email) ? $email : $user->id();
        return $this->trackCustomerEventBase($identifier, 'page', $url, $data) !== null;
    }

    public function trackUserEvent(User $user, string $name, $data): bool
    {
        $email = $user->email();
        $identifier = !empty($email) ? $email : $user->id();
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

    protected function getAuthorizationHeader($url)
    {
        if (preg_match('/^https\:\/\/track\.customer\.io\//', $url) === 1) {
            return sprintf('Basic %s', base64_encode($this->siteId . ':' . $this->trackingKey));
        }
        return sprintf('Bearer %s', $this->key);
    }
}
