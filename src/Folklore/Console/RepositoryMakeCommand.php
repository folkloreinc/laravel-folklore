<?php

namespace Folklore\Console;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

#[AsCommand(name: 'make:repository')]
class RepositoryMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:repository';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'make:repository';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a repository class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Repository';

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $model = $this->option('model') ?? Str::singular($name);
        $stub = $model ? $this->replaceModel($stub, $model) : $stub;

        $full = $this->option('full');
        $resource = $this->option('resource') ?? Str::singular($name);
        $stub = $full ? $this->replaceContract($stub, $name) : $stub;
        $stub = $full ? $this->replaceResource($stub, $resource) : $stub;

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
        $contractClass = $this->qualifyContract($name, 'Repositories');

        $replace = [
            'DummyFullContractClass' => $contractClass,
            '{{ namespacedContract }}' => $contractClass,
            '{{namespacedContract}}' => $contractClass,
            'DummyContractClass' => class_basename($contractClass),
            '{{ contract }}' => class_basename($contractClass),
            '{{contract}}' => class_basename($contractClass),
            '{{ contractAlias }}' => class_basename($contractClass) . 'RepositoryContract',
            '{{contractAlias}}' => class_basename($contractClass) . 'RepositoryContract',
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
        return $this->option('full')
            ? $this->resolveStubPath('/stubs/repository.full.stub')
            : $this->resolveStubPath('/stubs/repository.stub');
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
        return $rootNamespace . '\Repositories';
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
            [
                'resource',
                'r',
                InputOption::VALUE_REQUIRED,
                'The resource that the repository applies to.',
            ],
            ['full', 'f', InputOption::VALUE_NONE, 'Add repository contract and resource contract'],
        ];
    }
}
