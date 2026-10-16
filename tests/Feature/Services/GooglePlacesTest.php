<?php

namespace Folklore\Tests\Feature\Services;

use Folklore\Services\Google\Places;
use Folklore\Tests\TestCase;

class GooglePlacesTest extends TestCase
{
    public function test_the_app_url_is_sent_as_the_referer()
    {
        config(['app.url' => 'https://example.com']);

        $this->assertSame('https://example.com', $this->getRefererHeader($this->makePlaces()));
    }

    public function test_it_accepts_a_missing_app_url()
    {
        config(['app.url' => null]);

        $this->assertSame('', $this->getRefererHeader($this->makePlaces()));
    }

    protected function makePlaces()
    {
        return new class('api-key') extends Places
        {
            public function getHttpClient()
            {
                return $this->client->getHttpClient();
            }
        };
    }

    protected function getRefererHeader($places): string
    {
        return $places->getHttpClient()->getConfig('headers')['referer'];
    }
}
