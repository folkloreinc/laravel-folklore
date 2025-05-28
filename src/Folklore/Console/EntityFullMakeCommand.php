<?php

namespace Folklore\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EntityMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:entity {name} {--model=} {--repository=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an entity with contracts and repository';

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
        $model = $this->option('model') ?? '\\App\\Models\\'.$name;
        $repository = $this->option('repository') ?? Str::plural($name);

        $this->call('make:entity-contract', [
            'name' => $name
        ]);

        $this->call('make:entity-model', [
            'name' => $name,
            '--model' => $model
        ]);

        $this->call('make:repository-contract', [
            'name' => $repository,
            '--entity' => $name,
            '--full' => true,
        ]);

        $this->call('make:repository', [
            'name' => $repository,
            '--entity' => $name,
            '--model' => $model,
            '--full' => true,
        ]);
    }
}
