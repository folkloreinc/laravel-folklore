<?php

namespace Folklore\Contracts\Repositories;

use Illuminate\Contracts\Auth\UserProvider;
use Folklore\Contracts\Entities\User;

interface Users extends Entities, UserProvider
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function create($data): User;

    public function update(string $id, $data): ?User;
}
