<?php

namespace Folklore\Services\Google;

use Folklore\Support\Concerns\MakesRequests;
use Folklore\Contracts\Services\Google\Drive as DriveContract;

class Drive implements DriveContract
{
    use MakesRequests;

    public function __construct()
    {
    }

    public function loadCsvFromSheetUrl($url, $sheet = null): array
    {
        $id = $this->getIdFromUrl($url);
        $url = sprintf('https://docs.google.com/spreadsheets/d/%s/gviz/tq', $id);
        $data = $this->requestData($url, 'GET', [
            'tqx' => 'out:csv',
            'sheet' => $sheet,
        ]);
        $lines = explode("\n", $data);
        return array_map(function ($line) {
            return str_getcsv($line);
        }, $lines);
    }

    protected function getIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        return preg_match('/\/d\/([^\/]+)(\/.*)?$/', $path, $matches) === 1 ? $matches[1] : null;
    }
}
