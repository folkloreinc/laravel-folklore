<?php

namespace Folklore\Contracts\Repositories;

use Illuminate\Contracts\Auth\UserProvider;
use Folklore\Contracts\Resources\User;

interface Users extends Resources, UserProvider
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function create(array $data): User;

    public function update(string $id, array $data): ?User;
}
