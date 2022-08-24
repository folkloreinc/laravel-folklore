<?php

namespace Folklore\Contracts\Resources;

interface Document extends Media
{
    public function metadata(): DocumentMetadata;
}
