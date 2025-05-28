<?php

namespace Folklore\Contracts\Entities;

interface Document extends Media
{
    public function metadata(): DocumentMetadata;
}
