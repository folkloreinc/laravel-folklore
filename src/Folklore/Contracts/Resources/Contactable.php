<?php

namespace Folklore\Contracts\Resources;

interface Contactable
{
    public function toContact(): ?Contact;
}
