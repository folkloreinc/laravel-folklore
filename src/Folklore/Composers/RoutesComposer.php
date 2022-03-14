<?php

namespace Folklore\Composers;

use Illuminate\View\View;
use Folklore\Composers\Concerns\ComposesRoutes;

abstract class RoutesComposer
{
    use ComposesRoutes;

    protected $routes = [];

    protected $routesLocalized = [];

    public function compose(View $view)
    {
        $locale = app()->getLocale();
        $names = collect($this->routes)
            ->merge($this->getRoutesNamesWithLocales($this->routesLocalized))
            ->unique()
            ->values();
        $view->routes = $this->composeRoutesByNames($names, $locale);
    }
}
