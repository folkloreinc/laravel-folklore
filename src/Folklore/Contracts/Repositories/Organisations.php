<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Resources\Organisation;
use Folklore\Contracts\Resources\OrganisationMember;
use Folklore\Contracts\Resources\User;

interface Organisations extends Resources
{
    public function findById(string $id): ?Organisation;

    public function findBySlug(string $slug): ?Organisation;

    public function create(array $data): Organisation;

    public function update(string $id, array $data): ?Organisation;

    public function addMemberFromUser(string $id, User $user, array $data): ?OrganisationMember;
}
