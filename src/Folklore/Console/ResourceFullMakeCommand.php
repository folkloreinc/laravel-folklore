<?php

namespace Folklore\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResourceFullMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:resource-full {name} {--model=} {--repository=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a resource with contracts and repository';

    /**
     * Create a new controller creator command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  \Illuminate\Contracts\View\Factory  $files
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $model = $this->argument('model') ?? '\\App\\Models\\'.$name;
        $repository = $this->argument('repository') ?? Str::plural($name);

        $this->call('make:resource-contract', [
            'name' => $name
        ]);

        $this->call('make:resource-model', [
            'name' => $name,
            '--model' => $model
        ]);

        $this->call('make:repository-contract', [
            'name' => $repository,
            '--resource' => $name,
            '--full' => true,
        ]);

        $this->call('make:repository', [
            'name' => $repository,
            '--resource' => $name,
            '--model' => $model,
            '--full' => true,
        ]);
    }
}
