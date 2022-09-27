<?php

namespace Folklore\Composers\Concerns;

use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Routing\UrlGenerator;

trait ComposesRoutes
{
    public function composeRoutesByNames($routesNames, $options = []): array
    {
        $routesCollection = resolve(Router::class)->getRoutes();
        $routes = collect($routesNames)->map(function ($name) use ($routesCollection) {
            return $routesCollection->getByName($name);
        })->filter(function ($route) {
            return !is_null($route);
        })->values();
        return $this->composeRoutes($routes, $options);
    }

    public function composeRoutes($routes, $options = []): array
    {
        $options = is_string($options) ? [
            'namespaceToRemove' => $options
        ] : $options;
        $namespaceToRemove = Arr::get($options, 'namespaceToRemove');
        return collect($routes)->mapWithKeys(function ($route) use ($namespaceToRemove, $options) {
            $path = $this->getPathFromRoute($route, $options);
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

    protected function getPathFromRoute($route, $options = [])
    {
        $withoutParametersPatterns = Arr::get($options, 'withoutParametersPatterns', false);
        $name = $route->getName();
        if (empty($name)) {
            return null;
        }
        $patterns = array_merge(resolve(Router::class)->getPatterns(), $route->wheres ?? []);
        $parameters = $route->parameterNames();

        preg_match_all('/\{(.*?)\}/', $route->getDomain() . $route->uri(), $matches);

        $params = [];
        foreach ($parameters as $parameter) {
            $params[] = ':' . $parameter;
        }

        $path = resolve(UrlGenerator::class)->route($name, $params, false);

        if (!$withoutParametersPatterns) {
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
        }

        return $path;
    }
}
