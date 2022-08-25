<?php

namespace Folklore\Routing;

use Illuminate\Routing\Router;
use Folklore\Support\Concerns\AttachToMacroable;

class UrlGeneratorMixin
{
    use AttachToMacroable;

    protected $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function routeForReactRouter()
    {
        $router = $this->router;
        return function ($name, $opts = []) use ($router) {
            $route = is_string($name) ? $router->getRoutes()->getByName($name) : $name;
            if (is_null($route)) {
                return null;
            }
            $withoutPatterns = $opts['withoutPatterns'] ?? false;
            $patterns = $router->getPatterns();
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

            $path = url()->route($name, $params, false);
            foreach ($parameters as $parameter) {
                if (in_array($parameter, $optionalParameters)) {
                    $path = preg_replace(
                        '/(' . preg_quote(':' . $parameter) . ')\b/i',
                        '$1?',
                        $path
                    );
                }
                $pattern = data_get($route->wheres, $parameter, data_get($patterns, $parameter));
                if (isset($pattern) && !$withoutPatterns) {
                    $pattern = preg_replace('/^\(?(.*?)\)?$/', '$1', $pattern);
                    $path = preg_replace(
                        '/(' . preg_quote(':' . $parameter) . ')(\?)?\b/i',
                        '$1(' . $pattern . ')$2',
                        $path
                    );
                }
            }

            return $path;
        };
    }
}
