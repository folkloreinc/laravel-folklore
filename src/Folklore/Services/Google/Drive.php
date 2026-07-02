<?php

namespace Folklore\Services\Google;

use Folklore\Support\Concerns\MakesRequests;
use Folklore\Contracts\Services\Google\Drive as DriveContract;
use Illuminate\Support\Collection;
use ParseCsv\Csv;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

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

            // TODO: once php 8.4 is the minimum version, migrate to the native
            // Dom\HTMLDocument class: https://www.php.net/manual/en/class.dom-htmldocument.php

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            // Prepend an encoding hint so libxml parses the markup as UTF-8.
            $dom->loadHTML('<?xml encoding="UTF-8">' . $data, LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $table = $xpath->query('//tbody')->item(0);
            if ($table === null) {
                return null;
            }

            $headers = null;
            $items = [];
            foreach ($this->getElementChildren($table) as $row) {
                $columns = $this->getElementChildren($row);
                if (!isset($headers)) {
                    foreach ($columns as $column) {
                        $headers[] = html_entity_decode(
                            trim($column->textContent),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    }
                    continue;
                }
                $item = [];
                foreach ($columns as $index => $column) {
                    $image = $xpath->query('.//img', $column)->item(0);
                    if ($image !== null) {
                        $text = $image->getAttribute('src');
                        if (!empty($text)) {
                            $text = preg_replace('/w[0-9]+-h[0-9]+/', 'w2000-h2000', $text);
                        }
                    } else {
                        $inner = $xpath
                            ->query(
                                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' softmerge-inner ')]",
                                $column
                            )
                            ->item(0);
                        $inner = $inner ?? $column;
                        $innerIsLink =
                            sizeof($this->getElementChildren($inner)) === 1 &&
                            $xpath->query('.//a', $inner)->length === 1;
                        $text = $innerIsLink
                            ? trim($inner->textContent)
                            : trim($this->getInnerHtml($inner));
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

    /**
     * Return only the element children of a node (skipping text and comment nodes).
     *
     * @return DOMElement[]
     */
    protected function getElementChildren(DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }
        return $children;
    }

    /**
     * Serialize the inner HTML markup of a node.
     */
    protected function getInnerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return $html;
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
