<?php

namespace Folklore\Contracts\Services\Google;

interface Drive
{
    public function loadCsvFromSheetUrl($url, $sheet = null): array;
}
