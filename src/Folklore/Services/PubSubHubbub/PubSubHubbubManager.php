<?php

namespace Folklore\Services\PubSubHubbub;

use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Closure;
use Folklore\Contracts\Services\PubSubHubbub\Client;
use InvalidArgumentException;
use Folklore\Contracts\Services\PubSubHubbub\Factory;

class PubSubHubbubManager implements Factory
{
    /**
     * The container instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $container;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The array of created "hubs".
     *
     * @var array
     */
    protected $hubs = [];

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Foundation\Application  $container
     * @return void
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Get the default parser name.
     *
     * @return string
     */
    public function getDefaultHub()
    {
        return $this->container['config']->get('pubsubhubbub.hub');
    }

    /**
     * Create the default driver
     *
     * @param  array  $config
     * @return \Urbania\AppleNews\Contracts\Parser
     */
    protected function createDefaultDriver($config)
    {
        $url = is_string($config) ? $config : $config['url'];
        $secret = is_array($config) ? array_get($config, 'secret') : null;
        $opts = is_array($config) ? array_except($config, ['url', 'secret']) : [];
        return new PubSubHubbubClient($url, $secret, $opts);
    }

    /**
     * Get a parser instance.
     *
     * @param  string  $parser
     * @return \App\Contracts\Dialog\Service
     *
     * @throws \InvalidArgumentException
     */
    public function hub($hub = null): Client
    {
        $hub = isset($hub) ? $hub : $this->getDefaultHub();

        if (is_null($hub)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unable to resolve NULL hub for [%s].',
                    static::class
                )
            );
        }

        // If the given driver has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we'll just return that instance.
        if (!isset($this->hubs[$hub])) {
            $this->hubs[$hub] = $this->createHub($hub);
        }

        return $this->hubs[$hub];
    }

    /**
     * Create a new hub instance.
     *
     * @param  string  $parser
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createHub($parser)
    {
        $config = $this->getConfig($parser);
        $driver = is_array($config) ? array_get($config, 'driver', null) : null;

        // First, we will determine if a custom driver creator exists for the given driver and
        // if it does not we will check for a creator method for the driver. Custom creator
        // callbacks allow developers to build their own "drivers" easily using Closures.
        if (is_null($driver)) {
            return $this->createDefaultDriver($config, $parser);
        } elseif (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver, $config, $parser);
        } else {
            $method = 'create' . Str::studly($driver) . 'Driver';

            if (method_exists($this, $method)) {
                return $this->$method($config, $parser);
            }
        }
        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     * @param  array  $config
     * @param  string  $parser
     * @return mixed
     */
    protected function callCustomCreator($driver, $config, $parser)
    {
        return $this->customCreators[$driver]($this->container, $config, $parser);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param  string    $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "hubs".
     *
     * @return array
     */
    public function getHubs()
    {
        return $this->hubs;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->hub()->$method(...$parameters);
    }

    /**
     * Get the dialog parser configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return !is_null($name) ? $this->container['config']->get(
            "pubsubhubbub.hubs.{$name}",
            []
        ) : [
            'driver' => null
        ];
    }
}
