<?php

namespace Folklore\Broadcasters;

use PubNub\PubNub;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class PubNubBroadcaster extends Broadcaster
{
    /**
     * The PubNub SDK instance.
     *
     * @var \PubNub\PubNub
     */
    protected $pubnub;

    /**
     * The channel namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Create a new broadcaster instance.
     *
     * @param  \Pusher  $pusher
     * @return void
     */
    public function __construct(PubNub $pubnub, $namespace = null)
    {
        $this->pubnub = $pubnub;
        $this->namespace = $namespace;
    }

    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function auth($request)
    {
        if (
            Str::startsWith($request->channel_name, ['private-', 'presence-']) &&
            !$request->user()
        ) {
            throw new HttpException(403);
        }

        $channelName = Str::startsWith($request->channel_name, 'private-')
            ? Str::replaceFirst('private-', '', $request->channel_name)
            : Str::replaceFirst('presence-', '', $request->channel_name);

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    /**
     * Return the valid authentication response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $result
     * @return mixed
     */
    public function validAuthenticationResponse($request, $result)
    {
        if (Str::startsWith($request->channel_name, 'private')) {
            return null;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $payload = [
            'event' => $event,
            'data' => $payload,
        ];

        foreach ($channels as $channel) {
            $channel = $this->getChannelWithNamespace($channel);
            try {
                $result = $this->pubnub
                    ->publish()
                    ->channel($channel)
                    ->message($payload)
                    ->usePost(true)
                    ->sync();
            } catch (Exception $e) {
                Log::error($e);
            }
        }
    }

    /**
     * Get the channel with namespace
     *
     * @param string $channel The name of the channel
     * @return string
     */
    protected function getChannelWithNamespace($channel)
    {
        $parts = [];
        if (!empty($this->namespace)) {
            $parts[] = $this->namespace;
        }
        $parts[] = $channel;

        return implode(':', $parts);
    }

    /**
     * Get the PubNub SDK instance.
     *
     * @return \PubNub\PubNub
     */
    public function getPubNub()
    {
        return $this->pubnub;
    }

    /**
     * Get the channel namespace
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }
}
