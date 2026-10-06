<?php

namespace Folklore\Composers;

use Folklore\Composers\Concerns\ComposesRoutes;
use Illuminate\View\View;

abstract class RoutesComposer
{
    use ComposesRoutes;

    protected $routes = [];

    protected $routesLocalized = [];

    protected $withoutParametersPatterns = false;

    public function compose(View $view)
    {
        $locale = app()->getLocale();
        $names = collect($this->routes)
            ->merge($this->getRoutesNamesWithLocales($this->routesLocalized))
            ->unique()
            ->values();
        $view->routes = $this->composeRoutesByNames($names, [
            'namespaceToRemove' => $locale,
            'withoutParametersPatterns' => $this->withoutParametersPatterns,
        ]);
    }
}
