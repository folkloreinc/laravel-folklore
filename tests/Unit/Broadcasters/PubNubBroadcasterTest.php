<?php

namespace Folklore\Tests\Unit\Broadcasters;

use Folklore\Broadcasters\PubNubBroadcaster;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PubNubBroadcasterTest extends TestCase
{
    public function test_a_guest_cannot_authenticate_to_a_private_channel()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->makeBroadcaster()->auth($this->makeRequest('private-orders'));
    }

    public function test_a_guest_cannot_authenticate_to_a_presence_channel()
    {
        try {
            $this->makeBroadcaster()->auth($this->makeRequest('presence-room'));
            $this->fail('A guest should not be able to join a presence channel.');
        } catch (AccessDeniedHttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    protected function makeBroadcaster(): PubNubBroadcaster
    {
        // The PubNub SDK is an optional dependency and is not needed to check
        // the authorization of guests.
        return (new ReflectionClass(PubNubBroadcaster::class))->newInstanceWithoutConstructor();
    }

    protected function makeRequest(string $channel): Request
    {
        return Request::create('/broadcasting/auth', 'POST', ['channel_name' => $channel]);
    }
}
