<?php

namespace Folklore\Support;

use Illuminate\Support\ServiceProvider;

class ResourcesServiceProvider extends ServiceProvider
{
    protected $repositories = [];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        foreach ($this->repositories as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }
}
