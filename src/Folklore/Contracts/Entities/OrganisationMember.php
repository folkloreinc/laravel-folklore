<?php

namespace Folklore\Contracts\Entities;


interface OrganisationMember extends Entity
{
    public function organisation(): Organisation;

    public function user(): User;

    public function role(): ?string;
}
