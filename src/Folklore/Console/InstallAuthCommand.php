<?php

namespace Folklore\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Console\Command;

class InstallAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install files to handle authentification';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $filesystem = new Filesystem();

        // Controllers
        $filesystem->ensureDirectoryExists(app_path('Http/Controllers/Auth'));
        $filesystem->copyDirectory(__DIR__.'/../../stubs/auth/Controllers', app_path('Http/Controllers/Auth'));

        // Resources
        $filesystem->ensureDirectoryExists(app_path('Resources'));
        $filesystem->copy(__DIR__.'/../../stubs/auth/UserResource.php', app_path('Resources/User.php'));

        // Contract
        $filesystem->ensureDirectoryExists(app_path('Contracts/Resources'));
        $filesystem->copy(__DIR__.'/../../stubs/auth/UserContract.php', app_path('Contracts/Resources/User.php'));

        // Contract
        $filesystem->ensureDirectoryExists(app_path('Contracts/Repositories'));
        $filesystem->copy(__DIR__.'/../../stubs/auth/UsersRepository.php', app_path('Contracts/Repositories/Users.php'));
    }
}
