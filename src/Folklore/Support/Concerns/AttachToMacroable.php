<?php

namespace Folklore\Support\Concerns;

use ReflectionClass;
use ReflectionMethod;

trait AttachToMacroable
{
    public function attach($macroable)
    {
        $mixin = $this;
        $methods = (new ReflectionClass($mixin))->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            $name = $method->name;
            if ($name !== 'attach' && $name !== '__construct') {
                $macroable::macro($name, $this->{$name}());
            }
        }
    }
}
