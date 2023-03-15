<?php

namespace Folklore\Services\CustomerIo;

use Folklore\Contracts\Services\CustomerIo\Customer as CustomerContract;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class Customer implements CustomerContract
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function id(): string
    {
        return data_get($this->data, 'cio_id', data_get($this->data, 'identifiers.cio_id'));
    }

    public function email(): ?string
    {
        return data_get($this->data, 'email', data_get($this->data, 'attributes.email'));
    }

    public function phone(): ?string
    {
        return data_get($this->data, 'phone', data_get($this->data, 'attributes.phone'));
    }

    public function name(): ?string
    {
        return data_get($this->data, 'name', data_get($this->data, 'attributes.name'));
    }

    public function firstName(): ?string
    {
        return data_get($this->data, 'firstname', data_get($this->data, 'attributes.firstname'));
    }

    public function lastName(): ?string
    {
        return data_get($this->data, 'lastname', data_get($this->data, 'attributes.lastname'));
    }

    public function birthdate(): ?Carbon
    {
        $date = data_get($this->data, 'birthdate', data_get($this->data, 'attributes.birthdate'));
        return !empty($date) ? Carbon::parse($date) : null;
    }

    public function preferredLocale()
    {
        return data_get($this->data, 'locale', data_get($this->data, 'attributes.locale'));
    }

    public function createdAt(): ?Carbon
    {
        $date = data_get($this->data, 'created_at', data_get($this->data, 'attributes.created_at'));
        return !empty($date) ? Carbon::parse($date) : null;
    }

    public function subscriptionPreferences(): Collection
    {
        $data = data_get($this->data, 'attributes._cio_subscription_preferences_computed', null);
        $data = !empty($data) && is_string($data) ? json_decode($data, true) : $data;
        return collect(data_get($data, 'topics', []))
            ->map(function ($value, $key) {
                return new SubscriptionPreference($key, $value);
            })
            ->values();
    }
}
