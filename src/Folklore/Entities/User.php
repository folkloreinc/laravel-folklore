<?php

namespace Folklore\Entities;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\User as UserContract;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User implements HasModel, UserContract
{
    // Fortify only challenges users whose class uses this trait. Its methods
    // are overridden below to delegate to the model.
    use TwoFactorAuthenticatable;

    /**
     * The two-factor authentication columns of the model, which Fortify reads
     * as properties of the user. They are copied from the model when the
     * entity is created and when forceFill() changes the model.
     */
    public $two_factor_secret = null;

    public $two_factor_recovery_codes = null;

    public $two_factor_confirmed_at = null;

    protected $model;

    public function __construct(Authenticatable $model)
    {
        $this->model = $model;

        $this->syncTwoFactorAttributes();
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

    public function getModel(): Model
    {
        return $this->model;
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
     * Get the password name for the user.
     *
     * @return string
     */
    public function getAuthPasswordName()
    {
        return 'password';
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
        return ! is_null($this->model->email_verified_at);
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
     * Mark the given user's email as unverified.
     *
     * @return bool
     */
    public function markEmailAsUnverified()
    {
        return $this->model
            ->forceFill([
                'email_verified_at' => null,
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
        $this->model->notify(new VerifyEmail);
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

    public function save(array $options = [])
    {
        return $this->model->save($options);
    }

    /**
     * Fill the model with the given attributes, guarded or not. Fortify
     * uses it to update the user, before calling save().
     *
     * @return $this
     */
    public function forceFill(array $attributes)
    {
        $this->model->forceFill($attributes);

        $this->syncTwoFactorAttributes();

        return $this;
    }

    /**
     * Determine if two-factor authentication has been enabled.
     *
     * @return bool
     */
    public function hasEnabledTwoFactorAuthentication()
    {
        return $this->model->hasEnabledTwoFactorAuthentication();
    }

    /**
     * Get the user's two factor authentication recovery codes.
     *
     * @return array
     */
    public function recoveryCodes()
    {
        return $this->model->recoveryCodes();
    }

    /**
     * Replace the given recovery code with a new one in the user's stored codes.
     *
     * @param  string  $code
     * @return void
     */
    public function replaceRecoveryCode($code)
    {
        $this->model->replaceRecoveryCode($code);

        $this->syncTwoFactorAttributes();
    }

    /**
     * Get the QR code SVG of the user's two factor authentication QR code URL.
     *
     * @return string
     */
    public function twoFactorQrCodeSvg()
    {
        return $this->model->twoFactorQrCodeSvg();
    }

    /**
     * Get the two factor authentication QR code URL.
     *
     * @return string
     */
    public function twoFactorQrCodeUrl()
    {
        return $this->model->twoFactorQrCodeUrl();
    }

    /**
     * Copy the two-factor authentication columns of the model. The raw
     * attributes are read, so that a model without these columns doesn't
     * throw when it prevents accessing missing attributes.
     */
    protected function syncTwoFactorAttributes(): void
    {
        $attributes = $this->model->getAttributes();

        $this->two_factor_secret = $attributes['two_factor_secret'] ?? null;
        $this->two_factor_recovery_codes = $attributes['two_factor_recovery_codes'] ?? null;
        $this->two_factor_confirmed_at = $attributes['two_factor_confirmed_at'] ?? null;
    }
}
