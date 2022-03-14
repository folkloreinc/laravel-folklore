<?php

namespace App\Providers;

use Folklore\Support\ResourcesServiceProvider as BaseResourcesServiceProvider;

class ResourcesServiceProvider extends BaseResourcesServiceProvider
{
    protected $repositories = [
        \App\Contracts\Repositories\Users::class => \App\Repositories\Users::class,
    ];
}
