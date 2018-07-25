<?php

namespace Omatech\Editora\Connector;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Omatech\Editora\Connector\Commands\EditoraModernize;
use Omatech\Editora\Extractor\Extractor;
use Omatech\Editora\Loader\Loader;
use Omatech\Editora\Utils\Editora as Utils;
use Omatech\Editora\Connector\Commands\EditoraCreate;

class ConnectorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //Publicamos el archivo de configuración
        $this->publishes([
            __DIR__.'/Configuration.php' => config_path('editora.php'),
            __DIR__.'/editoradatabase_sample.php' => config_path('editoradatabase.php'),
        ]);

        //Publicamos los controllers
        $this->publishes([
            __DIR__.'/PreviewController.php' => app_path('Http/Controllers/Editora/PreviewController.php'),
            __DIR__.'/GlobalController.php' => app_path('Http/Controllers/Editora/GlobalController.php'),
        ]);

        //Publicamos los assets
        $this->publishes([
            __DIR__.'/Assets/js' => public_path('js'),
            __DIR__.'/Assets/css' => public_path('css'),
            __DIR__.'/Assets/images' => public_path('images'),
        ], 'public');

        //Rutas
        include __DIR__.'/Routes.php';

        //Directivas de Blade
        include __DIR__.'/Directives/GenerateEditLinkDirective.php';
        include __DIR__.'/Directives/GenerateEditoraEditScriptsDirective.php';
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/Configuration.php', 'editora'
        );

/*        $db = [
					'dbname' => env('DB_DATABASE'),
					'user' => env('DB_USERNAME'),
					'password' => env('DB_PASSWORD'),
					'host' => env('DB_HOST'),
					'driver' => 'pdo_mysql',
					'charset' => 'utf8'
        ]; 
*/
				$db=DB::connection()->getPdo();
        $this->app->bind('Extractor', function() use($db) {
            return new Extractor($db);
        });

        $this->app->bind('Utils', function() use ($db) {
            return new Utils($db);
        });
				
				$this->app->bind('Loader', function() use ($db) {
            return new Loader($db);
        });

        $laravelVersion = explode('.', $this->app->version());
        $laravelRelease = (int) $laravelVersion[1];

        $middlewareMethod = "middleware";
        if($laravelRelease >= 4) $middlewareMethod = "aliasMiddleware";

        $this->app['router']->$middlewareMethod('setLocale', 'Omatech\Editora\Connector\Middlewares\SetLocaleMiddleware');

        $this->registerHelpers();

        $this->commands([EditoraCreate::class, EditoraModernize::class]);
    }

    /**
     * Register helpers file
     */
    public function registerHelpers()
    {
        // Load the helpers in app/Http/helpers.php
        if (file_exists($file =  __DIR__.'/Helper/EditoraHelper.php'))
        {
            require $file;
        }
        if (file_exists($file =  __DIR__.'/Helper/StaticTextHelper.php'))
        {
            require $file;
        }
    }

}
