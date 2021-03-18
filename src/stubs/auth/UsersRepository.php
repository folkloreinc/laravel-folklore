<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\UserProvider;
use App\Contracts\Resources\User;

interface Users extends UserProvider, Resources
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function create($data): User;

    public function update(string $id, $data): ?User;

    public function updatePassword(string $id, string $password): ?User;
}
