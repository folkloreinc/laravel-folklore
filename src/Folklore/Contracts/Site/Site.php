<?php

namespace Folklore\Contracts\Site;

use Illuminate\Http\Request;

interface Site
{
    public function id(): string;

    public function matchRequest(Request $request): bool;
}
