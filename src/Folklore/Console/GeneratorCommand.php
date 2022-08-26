<?php

namespace Folklore\Console;

use Illuminate\Console\GeneratorCommand as BaseGeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class GeneratorCommand extends BaseGeneratorCommand
{
    /**
     * Replace the model for the given stub.
     *
     * @param  string  $stub
     * @param  string  $model
     * @return string
     */
    protected function replaceModel($stub, $model)
    {
        $modelClass = $this->parseModel($model);

        $replace = [
            'DummyFullModelClass' => $modelClass,
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}' => $modelClass,
            'DummyModelClass' => class_basename($modelClass),
            '{{ model }}' => class_basename($modelClass),
            '{{model}}' => class_basename($modelClass),
            '{{ modelAlias }}' => class_basename($modelClass) . 'Model',
            '{{modelAlias}}' => class_basename($modelClass) . 'Model',
            'DummyModelVariable' => lcfirst(class_basename($modelClass)),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{modelVariable}}' => lcfirst(class_basename($modelClass)),
        ];

        return str_replace(array_keys($replace), array_values($replace), $stub);
    }

    /**
     * Replace the resource contract for the given stub.
     *
     * @param  string  $stub
     * @param  string  $model
     * @return string
     */
    protected function replaceResource($stub, $name)
    {
        $resourceClass = $this->qualifyContract($name, 'Resources');

        $replace = [
            'DummyFullResourceClass' => $resourceClass,
            '{{ namespacedResource }}' => $resourceClass,
            '{{namespacedResource}}' => $resourceClass,
            'DummyResourceClass' => class_basename($resourceClass),
            '{{ resource }}' => class_basename($resourceClass),
            '{{resource}}' => class_basename($resourceClass),
            '{{ resourceAlias }}' => class_basename($resourceClass) . 'ResourceContract',
            '{{resourceAlias}}' => class_basename($resourceClass) . 'ResourceContract',
        ];

        return str_replace(array_keys($replace), array_values($replace), $stub);
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Qualify the given model class base name.
     *
     * @param  string  $model
     * @return string
     */
    protected function qualifyContract(string $class, string $type)
    {
        $class = ltrim($class, '\\/');

        $class = str_replace('/', '\\', $class);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($class, $rootNamespace)) {
            return $class;
        }

        return $rootNamespace.'Contracts\\'.$type.'\\'.$class;
    }
}
