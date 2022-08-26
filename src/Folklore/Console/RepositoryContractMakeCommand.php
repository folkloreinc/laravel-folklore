<?php

namespace Folklore\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

#[AsCommand(name: 'make:repository-contract')]
class RepositoryContractMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:repository-contract';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'make:repository-contract';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a repository contract';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'RepositoryContract';

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $full = $this->option('full');
        $resource = $this->option('resource') ?? Str::singular($name);
        $stub = $full ? $this->replaceResource($stub, $resource) : $stub;

        return $stub;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->option('full')
            ? $this->resolveStubPath('/stubs/repository-contract.full.stub')
            : $this->resolveStubPath('/stubs/repository-contract.stub');
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
        return $rootNamespace . '\Contracts\Repositories';
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
                'resource',
                'r',
                InputOption::VALUE_OPTIONAL,
                'The resource that the repository applies to.',
            ],
            ['full', 'f', InputOption::VALUE_NONE, 'Add repository contract and resource contract'],
        ];
    }
}
