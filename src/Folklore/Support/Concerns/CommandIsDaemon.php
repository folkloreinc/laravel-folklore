<?php

namespace Folklore\Support\Concerns;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

trait CommandIsDaemon
{
    protected $daemonShouldStop;

    protected $daemonMemoryLimit = 128;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $daemon = $this->option('daemon');
        $interval = (int) $this->option('interval');
        if ($daemon) {
            $lastRestart = $this->getTimestampOfLastDaemonRestart();
            $className = class_basename(get_class($this));
            while (true) {
                $startTime = time();
                $this->handleDaemon();
                $endTime = time();
                $wait = max(0, $interval - ($endTime - $startTime));
                if ($this->daemonShouldStop($lastRestart)) {
                    $this->line('[Daemon ' . $className . '] <info>Restarting daemon.</info>');
                    return;
                }
                $this->line(
                    '[Daemon ' .
                        $className .
                        '] <comment>Waiting:</comment> ' .
                        $wait .
                        ' second(s)'
                );
                sleep($wait);
            }
        } else {
            $this->handleDaemon();
        }
    }

    /**
     * Stop the process if necessary.
     *
     * @param  \Illuminate\Queue\WorkerOptions  $options
     * @param  int  $lastRestart
     * @param  mixed  $job
     */
    protected function daemonShouldStop($lastRestart)
    {
        if ($this->daemonShouldStop) {
            return true;
        } elseif ($this->memoryExceeded($this->daemonMemoryLimit)) {
            return true;
        } elseif ($this->daemonShouldRestart($lastRestart)) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param  int   $memoryLimit
     * @return bool
     */
    protected function memoryExceeded($memoryLimit)
    {
        return memory_get_usage(true) / 1024 / 1024 >= $memoryLimit;
    }

    /**
     * Determine if the queue worker should restart.
     *
     * @param  int|null  $lastRestart
     * @return bool
     */
    protected function daemonShouldRestart($lastRestart)
    {
        return $this->getTimestampOfLastDaemonRestart() != $lastRestart;
    }

    /**
     * Get the last queue restart timestamp, or null.
     *
     * @return int|null
     */
    protected function getTimestampOfLastDaemonRestart()
    {
        return resolve(CacheRepository::class)->get('console:daemon:restart');
    }
}
