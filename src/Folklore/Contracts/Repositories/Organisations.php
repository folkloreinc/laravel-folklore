<?php

namespace Folklore\Contracts\Repositories;

use Folklore\Contracts\Entities\Organisation;
use Folklore\Contracts\Entities\OrganisationMember;
use Folklore\Contracts\Entities\User;

interface Organisations extends Entities
{
    public function findById(string $id): ?Organisation;

    public function findBySlug(string $slug): ?Organisation;

    public function create($data): Organisation;

    public function update(string $id, $data): ?Organisation;

    public function addMemberFromUser(string $id, User $user, $data): ?OrganisationMember;
}
