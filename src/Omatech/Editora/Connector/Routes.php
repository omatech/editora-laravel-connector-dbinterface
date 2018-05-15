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

Route::group(['middleware' => ['web']], function()
{
    $routes = config('editora.routeParams');

    Route::get('/preview/{lang}/{nice_url}', 'App\Http\Controllers\Editora\PreviewController@index');

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

        Route::get($routeString, 'Omatech\Editora\Connector\EditoraController@init');
    }
});
