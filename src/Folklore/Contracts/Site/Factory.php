<?php

namespace Folklore\Contracts\Site;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface Factory
{
    public function site(string $id): Site;

    public function fromRequest(Request $request): ?Site;

    public function sites(): Collection;
}
