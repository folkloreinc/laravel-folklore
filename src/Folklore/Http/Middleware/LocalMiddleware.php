<?php

namespace Folklore\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\View;

class LocalMiddleware
{
    public function handle($request, Closure $next)
    {
        View::share('inWebpack', $request->header('x-webpack-dev-server', false) === 'true');

        return $next($request);
    }
}
