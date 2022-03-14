<?php

namespace Folklore\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory as FactoryContract;

class AssetsViewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assets:view {--output_path=assets} {--manifest-path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate views from assets manifest';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The view factory
     *
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $view;

    /**
     * Create a new controller creator command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  \Illuminate\Contracts\View\Factory  $files
     * @return void
     */
    public function __construct(Filesystem $files, FactoryContract $view)
    {
        parent::__construct();

        $this->files = $files;
        $this->view = $view;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $assetManifest = $this->option('manifest-path') ?? public_path('asset-manifest.json');
        $stubsPath = __DIR__ . '/../../stubs';
        $headStubPath = $stubsPath . '/assets-head.blade.php';
        $bodyStubPath = $stubsPath . '/assets-body.blade.php';

        $outputPath = rtrim(
            resource_path('views/' . ltrim($this->option('output_path'), '/')),
            '/'
        );
        $manifest = json_decode($this->files->get($assetManifest), true);

        $head = $this->view->file($headStubPath, $manifest)->render();
        $body = $this->view->file($bodyStubPath, $manifest)->render();

        $this->files->put($outputPath . '/head.blade.php', $head);
        $this->files->put($outputPath . '/body.blade.php', $body);
    }
}
