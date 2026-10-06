<?php

namespace Folklore\Tests\Feature\Services;

use Folklore\Notifications\CustomerIoWebhook;
use Folklore\Services\CustomerIo\Client;
use Folklore\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class CustomerIoClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    public function test_api_requests_use_the_app_api_key()
    {
        $this->makeClient()->findCustomerById('abc');

        Http::assertSent(fn (Request $request) => str_starts_with(
            $request->url(),
            'https://api.customer.io/v1/customers/abc/attributes'
        ) && $request->header('Authorization') === ['Bearer app-key']);
    }

    public function test_track_requests_use_the_tracking_credentials()
    {
        $this->makeClient()->updateCustomer('jane@example.com', ['name' => 'Jane']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://track.customer.io/api/v2/entity'
            && $request->header('Authorization') === ['Basic '.base64_encode('site-id:tracking-key')]);
    }

    public function test_customer_io_webhooks_use_the_app_api_key()
    {
        $this->makeClient()->triggerWebhook('https://api.customer.io/v1/webhook/123', ['id' => 1]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.customer.io/v1/webhook/123'
            && $request->header('Authorization') === ['Bearer app-key']);
    }

    public function test_webhooks_on_the_configured_region_use_the_app_api_key()
    {
        $this->makeClient('https://api-eu.customer.io')
            ->triggerWebhook('https://api-eu.customer.io/v1/webhook/123', ['id' => 1]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api-eu.customer.io/v1/webhook/123'
            && $request->header('Authorization') === ['Bearer app-key']);
    }

    public function test_other_hosts_never_receive_the_credentials()
    {
        $client = $this->makeClient();

        $client->triggerWebhook('https://hooks.example.com/notify', ['id' => 1]);
        $client->triggerWebhook('https://api.customer.io.example.com/v1/webhook/123', ['id' => 1]);

        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => $request->hasHeader('Authorization'));
    }

    public function test_webhook_from_id_uses_the_configured_api_base_url()
    {
        $this->assertSame('https://api.customer.io/v1/webhook/123', CustomerIoWebhook::fromId('123')->url);

        $this->app['config']->set('services.customerio.api_base_url', 'https://api-eu.customer.io/');

        $this->assertSame('https://api-eu.customer.io/v1/webhook/123', CustomerIoWebhook::fromId('123')->url);
    }

    protected function makeClient(?string $apiBaseUrl = null): Client
    {
        return new Client('app-key', 'site-id', 'tracking-key', $apiBaseUrl);
    }
}
