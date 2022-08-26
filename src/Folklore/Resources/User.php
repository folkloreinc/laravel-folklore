<?php

namespace Folklore\Resources;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Folklore\Contracts\Resources\User as UserContract;

class User implements UserContract
{
    protected $model;

    public function __construct(Authenticatable $model)
    {
        $this->model = $model;
    }

    public function id(): string
    {
        return $this->model->id;
    }

    public function name(): ?string
    {
        return $this->model->name;
    }

    public function email(): ?string
    {
        return $this->model->email;
    }

    public function role(): ?string
    {
        return $this->model->role;
    }

    /**
     * Determine if the entity has a given ability.
     *
     * @param  iterable|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function can($abilities, $arguments = [])
    {
        return $this->model->can($abilities, $arguments);
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->model->getAttribute($this->model->getKeyName());
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
     * @return string
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
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->model->email_verified_at);
    }

    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    public function markEmailAsVerified()
    {
        return $this->model
            ->forceFill([
                'email_verified_at' => $this->model->freshTimestamp(),
            ])
            ->save();
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->model->notify(new VerifyEmail());
    }

    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function getEmailForVerification()
    {
        return $this->model->email;
    }

    /**
     * Get the user's two factor authentication recovery codes.
     *
     * @return array
     */
    public function recoveryCodes()
    {
        return json_decode(decrypt($this->two_factor_recovery_codes), true);
    }

    public function save(array $options = [])
    {
        return $this->model->save($options);
    }
}
