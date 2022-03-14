<?php

namespace Folklore\Composers\Concerns;

use Illuminate\Routing\Router;
use Illuminate\Contracts\Routing\UrlGenerator;

trait ComposesRoutes
{
    public function composeRoutesByNames($routesNames, $namespaceToRemove = null): array
    {
        $routesCollection = resolve(Router::class)->getRoutes();
        $routes = collect($routesNames)->map(function ($name) use ($routesCollection) {
            return $routesCollection->getByName($name);
        })->filter(function ($route) {
            return !is_null($route);
        })->values();
        return $this->composeRoutes($routes, $namespaceToRemove);
    }

    public function composeRoutes($routes, $namespaceToRemove = null): array
    {
        return collect($routes)->mapWithKeys(function ($route) use ($namespaceToRemove) {
            $path = $this->getPathFromRoute($route);
            if (is_null($path)) {
                return [];
            }
            $map = [];
            $name = $route->getName();
            $key = !is_null($namespaceToRemove)
                ? preg_replace('/^'.preg_quote($namespaceToRemove, '/') . '\./', '$1', $name)
                : $name;
            $map[$key] = $path;
            return $map;
        })->toArray();
    }

    protected function getRoutesNamesWithLocales($names)
    {
        $locales = collect(config('locale.locales'));
        return collect($names)->reduce(function ($localizedRoutes, $routeName) use ($locales) {
            return $localizedRoutes->merge($locales->map(function ($locale) use ($routeName) {
                return $locale . '.' . $routeName;
            }));
        }, collect());
    }

    protected function getPathFromRoute($route)
    {
        $name = $route->getName();
        if (empty($name)) {
            return null;
        }
        $patterns = resolve(Router::class)->getPatterns();
        $parameters = $route->parameterNames();

        preg_match_all('/\{(.*?)\}/', $route->getDomain() . $route->uri(), $matches);
        $optionalParameters = array_map(
            function ($m) {
                return trim($m, '?');
            },
            array_values(
                array_filter($matches[1], function ($m) {
                    return preg_match('/\?$/', $m) === 1;
                })
            )
        );

        $params = [];
        foreach ($parameters as $parameter) {
            $params[] = ':' . $parameter;
        }

        $path = resolve(UrlGenerator::class)->route($name, $params, false);
        foreach ($parameters as $parameter) {
            if (in_array($parameter, $optionalParameters)) {
                $path = preg_replace('/(' . preg_quote(':' . $parameter) . ')\b/i', '$1?', $path);
            }
            if (isset($patterns[$parameter])) {
                $pattern = preg_replace('/^\(?(.*?)\)?$/', '$1', $patterns[$parameter]);
                $path = preg_replace(
                    '/(' . preg_quote(':' . $parameter) . ')(\?)?\b/i',
                    '$1(' . $pattern . ')$2',
                    $path
                );
            }
        }

        return $path;
    }
}
