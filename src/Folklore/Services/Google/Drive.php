<?php

namespace Folklore\Services\Google;

use Folklore\Support\Concerns\MakesRequests;
use Folklore\Contracts\Services\Google\Drive as DriveContract;
use Illuminate\Support\Collection;
use ParseCsv\Csv;
use PHPHtmlParser\Dom;

class Drive implements DriveContract
{
    use MakesRequests;

    public function __construct()
    {
    }

    public function loadItemsFromSheetUrl($url): ?Collection
    {
        if (preg_match('/\/d\/e\//', $url) === 1) {
            $id = $this->getPublicationIdFromUrl($url);
            $gid = preg_match('/gid=([^&]+)/', $url, $matches) === 1 ? $matches[1] : null;
            $url = sprintf(
                'https://docs.google.com/spreadsheets/d/e/%s/pubhtml/sheet?headers=false&gid=%s',
                $id,
                $gid ?? '0'
            );
            $data = $this->requestData($url, 'GET');
            $dom = new Dom();
            $dom->loadStr($data);
            $table = $dom->find('tbody');
            $rows = $table->getChildren();
            $headers = null;
            $items = [];
            foreach ($rows as $row) {
                $columns = $row->getChildren();
                if (!isset($headers)) {
                    foreach ($columns as $column) {
                        $headers[] = trim($column->text);
                    }
                    continue;
                }
                $item = [];
                foreach ($columns as $index => $column) {
                    $image = $column->find('img');
                    if ($image->count() > 0) {
                        $text = $image->getAttribute('src');
                        if (!empty($text)) {
                            $text = preg_replace('/w[0-9]+-h[0-9]+/', 'w2000-h2000', $text);
                        }
                    } else {
                        $inner = $column->find('.softmerge-inner');
                        $inner = $inner->count() > 0 ? $inner : $column;
                        $innerIsLink =
                            sizeof($inner->getChildren()) === 1 && $inner->find('a')->count() === 1;
                        $text = $innerIsLink ? trim($inner->innerText) : trim($column->innerHtml);
                    }
                    $key = $headers[$index];
                    if (!empty($text) && !empty($key)) {
                        $item[$key] = $text;
                    }
                }
                if (!empty($item)) {
                    $items[] = $item;
                }
            }
            return !empty($items) ? collect($items) : null;
        }
        $gid = preg_match('/gid=([^&]+)/', $url, $matches) === 1 ? $matches[1] : null;
        $items = $this->loadCsvFromSheetUrl($url, $gid);
        return !empty($items) ? collect($items) : null;
    }

    public function loadCsvFromSheetUrl($url, $sheet = null): array
    {
        $id = $this->getIdFromUrl($url);
        $url = sprintf('http://docs.google.com/spreadsheets/d/%s/export', $id);
        $data = $this->requestData(
            $url,
            'GET',
            array_merge(
                [
                    'format' => 'csv',
                ],
                !empty($sheet)
                    ? [
                        'gid' => $sheet,
                    ]
                    : []
            )
        );

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

    protected function getPublicationIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        return preg_match('/\/d\/e\/([^\/]+)(\/.*)?$/', $path, $matches) === 1 ? $matches[1] : null;
    }
}
