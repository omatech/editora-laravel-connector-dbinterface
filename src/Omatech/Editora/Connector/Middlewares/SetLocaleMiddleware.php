<?php

namespace Omatech\Editora\Connector\Middlewares;

use Closure;
use App;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        App::setLocale($request->route('language'));
        return $next($request);
    }
}
