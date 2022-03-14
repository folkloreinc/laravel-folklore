<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\View\Factory as ViewFactory;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $view = $this->app[ViewFactory::class];
        $view->composer('meta.*', \App\View\Composers\MetaComposer::class);
        $view->composer('app', \App\View\Composers\AppComposer::class);
    }
}
