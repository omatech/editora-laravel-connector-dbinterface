<?php

namespace Omatech\Editora\Connector;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Omatech\Editora\Connector\Commands\EditoraModernize;
use Omatech\Editora\Extractor\Extractor;
use Omatech\Editora\Loader\Loader;
use Omatech\Editora\DBInterfaceBase as Utils;
use Omatech\Editora\Connector\Commands\EditoraCreate;

use Omatech\Editora\Connector\Commands\EditoraFakeContent;
use Omatech\Editora\Connector\Commands\EditoraCreateMVC;
use Omatech\Editora\Connector\Commands\EditoraRemoveContent;
use Omatech\Editora\Connector\Commands\EditoraRegeneratePasswords;
use Omatech\Editora\Connector\Commands\EditoraEncryptPasswords;

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
            __DIR__.'/base.blade.php' => resource_path('views/layouts/app.blade.php'),
            __DIR__.'/404.blade.php' => resource_path('views/errors/404.blade.php'),
            __DIR__.'/pagination_editora.blade.php' => resource_path('views/components/pagination_editora.blade.php'),
        ]);

        //Publicamos los assets
        $this->publishes([
            __DIR__.'/Assets/js' => public_path('js'),
            __DIR__.'/Assets/css' => public_path('css'),
            __DIR__.'/Assets/images' => public_path('images'),
        ], 'public');

        //Publicamos las exceptions
        $this->publishes([
            __DIR__.'/Exceptions/EditoraNotFoundHttpException.php' => app_path('Exceptions/EditoraNotFoundHttpException.php'),
        ]);

        //Directivas de Blade
        include __DIR__.'/Directives/GenerateEditLinkDirective.php';
        include __DIR__.'/Directives/GenerateEditoraEditScriptsDirective.php';
    }

    private function toDoctrineDB($config)
    {
        $driverSchemeAliases = [
            'mysql'    => 'pdo_mysql',
            'mariadb'  => 'pdo_mysql',
            'postgres' => 'pdo_pgsql',
            'sqlite'   => 'pdo_sqlite',
            'sqlsrv'   => 'pdo_sqlsrv',
        ];
        
        $config['driver'] = $driverSchemeAliases[$config['driver']] ?? $config['driver'];
        $config['user'] = $config['username'] ?? '';
        $config['dbname'] = $config['database'];
        $config['driverOptions'] = $config['options'] ?? [];
        if($config['unix_socket'] ?? false) {
            unset($config['host']);
        }
        return $config;
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/Configuration.php',
            'editora'
        );

        $db = DB::connection()->getConfig();
        $db = $this->toDoctrineDB($db);

        $this->app->bind('Extractor', function () use ($db) {
            return new Extractor($db);
        });

        $this->app->bind('Utils', function () use ($db) {
            return new Utils($db);
        });

        $this->app->bind('Loader', function () use ($db) {
            return new Loader($db);
        });

        $laravelVersion = explode('.', $this->app->version());
        $laravelRelease = (int) $laravelVersion[1];

        $middlewareMethod = "middleware";
        if ($laravelRelease >= 4) {
            $middlewareMethod = "aliasMiddleware";
        }

        $this->app['router']->$middlewareMethod('setLocale', 'Omatech\Editora\Connector\Middlewares\SetLocaleMiddleware');

        $this->registerHelpers();

        $this->commands([EditoraCreate::class, EditoraModernize::class, EditoraFakeContent::class, EditoraCreateMVC::class, EditoraRemoveContent::class, EditoraRegeneratePasswords::class, EditoraEncryptPasswords::class]);
    }

    /**
     * Register helpers file
     */
    public function registerHelpers()
    {
        // Load the helpers in app/Http/helpers.php
        if (file_exists($file =  __DIR__.'/Helper/EditoraHelper.php')) {
            require $file;
        }
        if (file_exists($file =  __DIR__.'/Helper/StaticTextHelper.php')) {
            require $file;
        }
    }
}
