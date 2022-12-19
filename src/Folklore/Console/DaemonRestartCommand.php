<?php

namespace Folklore\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\InteractsWithTime;

class DaemonRestartCommand extends Command
{
    use InteractsWithTime;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daemon:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart daemons';

    protected $cache;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CacheRepository $cache)
    {
        parent::__construct();
        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->cache->forever('console:daemon:restart', $this->currentTime());

        $this->info('Broadcasting daemon restart signal.');
    }
}
