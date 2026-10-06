<?php

namespace Folklore\Tests\Feature\Stubs;

use Folklore\Tests\TestCase;

class AssetsHeadStubTest extends TestCase
{
    public function test_it_preloads_scripts_and_links_stylesheets()
    {
        $html = $this->app['view']
            ->file(__DIR__.'/../../../src/stubs/assets-head.blade.php', [
                'entrypoints' => [
                    'static/js/runtime-main.js',
                    'static/js/main.1234.js',
                    'static/css/main.5678.css',
                ],
            ])
            ->render();

        $this->assertStringContainsString(
            '<link href="/static/js/main.1234.js" rel="preload" as="script" />',
            $html,
        );
        $this->assertStringContainsString(
            '<link href="/static/css/main.5678.css" rel="stylesheet" type="text/css" />',
            $html,
        );
        $this->assertStringNotContainsString('runtime-main', $html);
        $this->assertStringNotContainsString(' ref="', $html);
    }
}
