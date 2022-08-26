<?php

namespace Folklore\Console;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

#[AsCommand(name: 'make:resource-model')]
class ResourceModelMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:resource-model';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'make:resource-model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a resource model class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Resource';

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $model = $this->option('model');
        $stub = $model ? $this->replaceModel($stub, $model) : $stub;
        $stub = $this->replaceResource($stub, $name);
        $stub = $this->replaceContract($stub, $name);

        return $stub;
    }

    /**
     * Replace the contract for the given stub.
     *
     * @param  string  $stub
     * @param  string  $model
     * @return string
     */
    protected function replaceContract($stub, $name)
    {
        $contractClass = $this->qualifyContract($name, 'Resources');

        $replace = [
            'DummyFullContractClass' => $contractClass,
            '{{ namespacedContract }}' => $contractClass,
            '{{namespacedContract}}' => $contractClass,
            'DummyContractClass' => class_basename($contractClass),
            '{{ contract }}' => class_basename($contractClass),
            '{{contract}}' => class_basename($contractClass),
            '{{ contractAlias }}' => class_basename($contractClass) . 'Contract',
            '{{contractAlias}}' => class_basename($contractClass) . 'Contract',
        ];

        return str_replace(array_keys($replace), array_values($replace), $stub);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/resource-model.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . '/../..' . $stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Resources';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            [
                'model',
                'm',
                InputOption::VALUE_REQUIRED,
                'The model that the repository applies to.',
            ],
        ];
    }
}
