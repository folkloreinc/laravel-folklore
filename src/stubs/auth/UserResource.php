<?php

namespace App\Resources;

use Illuminate\Support\Collection;
use App\Contracts\Resources\Resourcable;
use App\Contracts\Resources\User as UserContract;
use App\Models\User as UserModel;

class User implements UserContract
{
    protected $model;

    protected $data;

    public function __construct(UserModel $model)
    {
        $this->model = $model;
        $this->data = $model->data;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function name(): string
    {
        return $this->model->name;
    }

    public function email(): string
    {
        return $this->model->email;
    }

    public function role(): string
    {
        return 'user';
    }

    public function preferredLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Get the key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->model->id;
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return $this->model->getAuthIdentifierName();
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->model->getAuthIdentifier();
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->model->getAuthPassword();
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string|null
     */
    public function getRememberToken()
    {
        return $this->model->getRememberToken();
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value)
    {
        return $this->model->setRememberToken($value);
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return $this->model->getRememberTokenName();
    }

    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function hasVerifiedEmail()
    {
        return $this->model->hasVerifiedEmail();
    }

    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    public function markEmailAsVerified()
    {
        return $this->model->markEmailAsVerified();
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        return $this->model->sendEmailVerificationNotification();
    }

    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function getEmailForVerification()
    {
        return $this->model->getEmailForVerification();
    }

    /**
     * Get the e-mail address where password reset links are sent.
     *
     * @return string
     */
    public function getEmailForPasswordReset()
    {
        return $this->model->getEmailForPasswordReset();
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        return $this->model->sendPasswordResetNotification($token);
    }

    /**
     * Determine if the entity has a given ability.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function can($ability, $arguments = [])
    {
        return $this->model->can($ability, $arguments);
    }
}
