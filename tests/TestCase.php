<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as FrameworkTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Omatech\Editora\Connector\EditoraController;
use Omatech\Editora\Connector\ConnectorServiceProvider;
use Omatech\Editora\Connector\Media\PublicMediaController;

abstract class TestCase extends FrameworkTestCase
{
    private string $fixture;

    public function createApplication()
    {
        $this->fixture = sys_get_temp_dir().'/editora-connector-'.bin2hex(random_bytes(8));
        foreach (['bootstrap/cache', 'storage/framework/views', 'storage/logs'] as $folder) {
            mkdir($this->fixture.'/'.$folder, 0700, true);
        }
        $app = Application::configure(basePath: $this->fixture)
            ->withProviders([ConnectorServiceProvider::class])
            ->withExceptions()
            ->withMiddleware()
            ->withRouting(using: static function (): void {
                Route::match(['GET', 'HEAD'], '/uploads/{path}', PublicMediaController::class)->where('path', '.*');
            })->create();
        $app->afterBootstrapping(LoadConfiguration::class, static function (Application $app): void {
            $database = getenv('CONNECTOR_TEST_MYSQL') === '1'
                ? ['driver' => 'mysql', 'host' => 'mysql', 'port' => 3306, 'database' => 'connector_fixture', 'username' => 'fixture', 'password' => 'fixture-only', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '']
                : ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
            $app['config']->set([
                'app.key' => 'base64:'.base64_encode(str_repeat('f', 32)),
                'database.default' => $database['driver'],
                'database.connections.'.$database['driver'] => $database,
                'cache.default' => 'array', 'session.driver' => 'array',
                'logging.default' => 'null', 'logging.channels.null' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class],
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function preparePreviewRoutes(): void
    {
        // The connector skips its HTTP constructor in CLI; exercise the HTTP path in the test kernel.
        new \ReflectionProperty(Application::class, 'isRunningInConsole')->setValue($this->app, false);
        config(['editora.availableLanguages' => ['ca'], 'editora.defaultLanguage' => 'ca',
            'editora.forcedLanguage' => '', 'editora.ignoreBrowserLanguage' => true,
            'editora.controllersNamespace' => 'Tests\\Fixtures\\',
            'editora.notFoundHttpException' => NotFoundHttpException::class]);
        Route::middleware('web')->get('/{language}/{nice_url}', [EditoraController::class, 'init']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }
}
