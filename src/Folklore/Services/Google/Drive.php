<?php

namespace Folklore\Services\Google;

use Folklore\Support\Concerns\MakesRequests;
use Folklore\Contracts\Services\Google\Drive as DriveContract;
use ParseCsv\Csv;

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
            'headers' => 0,
        ]);

        $csv = new Csv();
        $csv->heading = false;
        $csv->parse($data);
        return $csv->data;
    }

    protected function getIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        return preg_match('/\/d\/([^\/]+)(\/.*)?$/', $path, $matches) === 1 ? $matches[1] : null;
    }
}
