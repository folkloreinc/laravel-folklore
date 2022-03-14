<?php

namespace Folklore\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory as FactoryContract;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'folklore:install {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install folklore files';

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
        $force = $this->option('force');
        $stubsPath = __DIR__ . '/../../stubs';

        $files = [
            $stubsPath . '/AppComposer.php' => app_path('View/Composers/AppComposer.php'),
            $stubsPath . '/UserContract.php' => app_path('Contracts/Resources/User.php'),
            $stubsPath . '/UsersContract.php' => app_path('Contracts/Repositories/Users.php'),
            $stubsPath . '/UserResource.php' => app_path('Resources/User.php'),
            $stubsPath . '/UserModel.php' => app_path('Models/User.php'),
            $stubsPath . '/UsersRepositories.php' => app_path('Repositories/Users.php'),
        ];

        foreach ($files as $stub => $destination) {
            $folder = dirname($destination);
            if (!$this->files->exists($folder)) {
                $this->line('<comment>Creating:</comment> Folder ' . $folder);
                $this->files->mkdir($folder, 0755, true);
            }

            $exists = $this->files->exists($destination);
            // prettier-ignore
            if ($exists && !$force &&
                !$this->confirm('Would you like to overwrite "' . $destination . '" ?')
            ) {
                $this->line('<comment>Skipping:</comment> '.$destination);
                continue;
            }

            if ($exists) {
                $this->line('<comment>Deleting:</comment> ' . $destination);
                $this->files->unlink($destination);
            }

            $this->files->copy($stub, $destination);
            $this->line('<info>Copied:</info> ' . $destination);
        }
    }
}
