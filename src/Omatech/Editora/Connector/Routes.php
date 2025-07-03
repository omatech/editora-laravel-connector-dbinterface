<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

if (config('editora.useFrontendRoutes', true))
{
    Route::group(['middleware' => ['web']], function()
    {
        $routes = config('editora.routeParams');

        $argv = request()->server->get('argv') ?? [];

        if(in_array('artisan', $argv) && !in_array('optimize', $argv)) {
            return false;
        }

        Route::get('/preview/{lang}/{nice_url}', config('editora.controllersNamespace', 'App\\Http\\Controllers\\Editora\\').'PreviewController@index');

        foreach($routes as $route)
        {
            $routeString = '';

            foreach($route as $param)
            {
                if(preg_match('/::/i', $param)) {
                    $routeString .= '/'.ltrim($param, ':');
                } else {
                    $routeString .= '/{'.$param.'?}';
                }
            }

            $editoraRoute = Route::get($routeString, 'Omatech\Editora\Connector\EditoraController@init');

            if (config('editora.fullNiceUrlInterpretation')) {
                $editoraRoute->where('nice_url', '.*');
            }
        }
    });
}
