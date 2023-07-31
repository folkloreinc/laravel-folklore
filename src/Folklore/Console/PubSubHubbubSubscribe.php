<?php

namespace Folklore\Console;

use Illuminate\Console\Command;

use Folklore\Contracts\Services\PubSubHubbub\Factory;

class PubSubHubbubSubscribe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pubsubhubbub:subscribe {topic} {callback} {--hub=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to hub';

    protected $manager;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Factory $manager)
    {
        parent::__construct();

        $this->manager = $manager;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $hub = $this->option('hub');
        $topic = $this->argument('topic');
        $callback = $this->argument('callback');
        $client = $this->manager->hub($hub);
        $this->line('<comment>Subscribing:</comment> Topic ' . $topic . '...');
        $response = $client->subscribe(
            filter_var($callback, FILTER_VALIDATE_URL) === false ? route($callback) : $callback,
            $topic
        );
        if ($response !== true) {
            $this->line('<error>Error:</error> ' . $response);
        } else {
            $this->line('<info>Subscribed:</info> Topic ' . $topic . '.');
        }
    }
}
