<?php

namespace Folklore\Contracts\Services\Google\Places;

use Illuminate\Support\Collection;

interface LocationMetadata
{
    public function categories(): ?Collection;

    public function types(): ?Collection;

    public function regions(): ?Collection;
}
