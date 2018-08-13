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

use Omatech\Editora\Connector\Commands\EditoraFakeContent;
use Omatech\Editora\Connector\Commands\EditoraCreateMVC;

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
            __DIR__.'/editoradatabase_sample.php' => config_path('editoradatabase.php')
        ]);

        //Publicamos los controllers
        $this->publishes([
            __DIR__.'/PreviewController.php' => app_path('Http/Controllers/Editora/PreviewController.php'),
            __DIR__.'/GlobalController.php' => app_path('Http/Controllers/Editora/GlobalController.php'),
						__DIR__.'/Error404.php' => app_path('Http/Controllers/Editora/Error404.php'),
            __DIR__.'/base.blade.php' => resource_path('views/base.blade.php'),
            __DIR__.'/404.blade.php' => resource_path('views/editora/404.blade.php'),
            __DIR__.'/package.json' => resource_path('package.json'),
					
        ]);

        //Publicamos los assets
        $this->publishes([
            __DIR__.'/Assets/js' => public_path('js'),
            __DIR__.'/Assets/css' => public_path('css'),
            __DIR__.'/Assets/images' => public_path('images'),
            __DIR__.'/Assets/favicon' => public_path('favicon'),
					
            __DIR__.'/Assets/markup/fonts' => resource_path('assets/markup/fonts'),
            __DIR__.'/Assets/markup/img' => resource_path('assets/markup/img'),
            __DIR__.'/Assets/markup/js' => resource_path('assets/markup/js'),
            __DIR__.'/Assets/markup/scss' => resource_path('assets/markup/scss'),
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
				$db=DB::connection()->getDoctrineConnection();
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

        $this->commands([EditoraCreate::class, EditoraModernize::class, EditoraFakeContent::class, EditoraCreateMVC::class]);
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
