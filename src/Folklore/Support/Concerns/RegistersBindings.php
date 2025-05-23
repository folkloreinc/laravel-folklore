<?php

namespace Folklore\Support\Concerns;

trait RegistersBindings
{
    protected function registerBindingsFromConfig($classes, $bindings)
    {
        foreach ($bindings as $variable => $configKey) {
            $this->app
                ->when($classes)
                ->needs($variable)
                ->give(function () use ($configKey) {
                    return is_array($configKey)
                        ? $this->app['config']->get($configKey[0], $configKey[1])
                        : $this->app['config']->get($configKey);
                });
        }
    }
}
