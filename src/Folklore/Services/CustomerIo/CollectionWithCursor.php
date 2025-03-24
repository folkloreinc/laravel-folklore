<?php

namespace Folklore\Services\CustomerIo;

use Illuminate\Support\Collection;

class CollectionWithCursor extends Collection
{
    protected $cursor;

    public function setCursor($cursor)
    {
        $this->cursor = $cursor;
        return $this;
    }

    public function cursor()
    {
        return $this->cursor;
    }
}
