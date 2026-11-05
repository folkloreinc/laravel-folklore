<?php

namespace Folklore\Panneau\Resources;

use Folklore\Contracts\Entities\User as UserContract;
use Folklore\Contracts\Repositories\Users as UsersRepository;
use Folklore\Panneau\Fields\Role;
use Folklore\Panneau\Resources\Concerns\EditsFields;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Panneau\Fields\Email;
use Panneau\Fields\Field;
use Panneau\Fields\Password;
use Panneau\Fields\Text;
use Panneau\Forms\ValidationContext;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;

/**
 * The users of the admin, on the auth module's users repository (docs/decisions/0025): their
 * name, email, password and role. A site extends it, even empty, and registers its class:
 *
 * ```php
 * class Users extends \Folklore\Panneau\Resources\Users {}
 * ```
 *
 * - The password is hashed by the repository, and never sent back: an empty one keeps the
 *   current password.
 * - The role is one of the roles of the roles module of `laravel-panneau`, or of
 *   `DefaultRoles` (`Role`).
 * - Each field comes from its own method (`nameField()`…), and `fields()` lists them, as in
 *   `Pages`. The list sorts by name and email.
 */
class Users extends Resource
{
    use EditsFields;

    public function repository(): ?string
    {
        return UsersRepository::class;
    }

    public function itemClass(): ?string
    {
        return UserContract::class;
    }

    public function singularLabel(): string
    {
        return static::trans('resources.users.singular');
    }

    public function pluralLabel(): string
    {
        return static::trans('resources.users.plural');
    }

    public function icon(): ?string
    {
        return 'users';
    }

    public function open(): string
    {
        return 'edit';
    }

    public function fields(): array
    {
        return [
            $this->nameField(),
            $this->emailField(),
            $this->passwordField(),
            $this->roleField(),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['name', 'email', 'role'])->sorts(['name', 'email']);
    }

    protected function nameField(): Field
    {
        return Text::make('name')
            ->label(static::trans('fields.name'))
            ->required()
            ->resolveUsing(fn (object $user): ?string => $user instanceof UserContract ? $user->name() : null);
    }

    /**
     * An email that no other user has.
     */
    protected function emailField(): Field
    {
        return Email::make('email')
            ->label(static::trans('fields.email'))
            ->required()
            ->resolveUsing(fn (object $user): ?string => $user instanceof UserContract ? $user->email() : null)
            ->rules(fn (ValidationContext $context): array => [
                Rule::unique($this->usersTable(), 'email')->ignore($context->item instanceof UserContract ? $context->item->id() : null),
            ]);
    }

    /**
     * Hashed by the repository, which keeps the current password when it is empty.
     */
    protected function passwordField(): Field
    {
        return Password::make('password')
            ->hashed(false)
            ->label(static::trans('fields.password'))
            ->help(static::trans('fields.password_help'));
    }

    protected function roleField(): Field
    {
        return Role::make('role')->label(static::trans('fields.role'));
    }

    /**
     * The table of the users: the users repository's model's, or `users`.
     */
    protected function usersTable(): string
    {
        $repository = Container::getInstance()->make(UsersRepository::class);
        $model = method_exists($repository, 'getModel') ? $repository->getModel() : null;

        return is_string($model) && is_a($model, Model::class, true) ? (new $model)->getTable() : 'users';
    }
}
