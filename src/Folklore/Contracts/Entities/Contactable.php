<?php

namespace Folklore\Contracts\Entities;

interface Contactable
{
    public function toContact(): ?Contact;
}
