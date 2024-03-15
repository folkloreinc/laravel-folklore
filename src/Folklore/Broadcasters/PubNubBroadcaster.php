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
     * Config
     *
     * @var string
     */
    protected $config = [];

    /**
     * Create a new broadcaster instance.
     *
     * @param  \Pusher  $pusher
     * @return void
     */
    public function __construct(PubNub $pubnub, $namespace = null, $config = [])
    {
        $this->pubnub = $pubnub;
        $this->namespace = $namespace;
        $this->config = $config;
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

        $checkPresence = data_get(
            $payload,
            'check_presence',
            data_get($this->config, 'check_presence', false)
        );
        $presence = $checkPresence ? $this->getPresence($channels) : null;
        foreach ($channels as $channel) {
            $channel = $this->getChannelWithNamespace($channel);
            $hasPresence = data_get($presence, $channel, true);
            if (!$hasPresence) {
                continue;
            }
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

    protected function getPresence($channels)
    {
        try {
            $result = $this->pubnub
                ->hereNow()
                ->channels(
                    collect($channels)
                        ->map(function ($channel) {
                            return $this->getChannelWithNamespace($channel);
                        })
                        ->toArray()
                )
                ->sync();
            return collect($result->getChannels())
                ->mapWithKeys(function ($channel) {
                    return [
                        $channel->getChannelName() => $channel->getOccupancy() > 0,
                    ];
                })
                ->toArray();
        } catch (Exception $e) {
            Log::error($e);
        }
        return null;
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
