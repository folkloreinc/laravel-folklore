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
            $url = sprintf('https://docs.google.com/spreadsheets/d/e/%s/pubhtml/sheet', $id);
            $data = $this->requestData($url, 'GET', [
                'headers' => 'false',
                'gid' => $gid ?? '0',
            ]);
            if (empty($data)) {
                return null;
            }

            // TODO: migrate to this native class, php 8.4
            // https://www.php.net/manual/en/class.dom-htmldocument.php

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
                        $headers[] = html_entity_decode(trim($column->text), ENT_QUOTES, 'UTF-8');
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
                        $text = $innerIsLink ? trim($inner->innerText) : trim($inner->innerHtml);
                    }
                    $key = $headers[$index];
                    if (!empty($text) && !empty($key)) {
                        $item[$key] = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
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
