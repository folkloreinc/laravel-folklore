<?php

namespace Folklore\Contracts\Services\Google;

use Illuminate\Support\Collection;

interface Drive
{
    public function loadItemsFromSheetUrl($url): ?Collection;

    public function loadCsvFromSheetUrl($url, $sheet = null): array;
}
