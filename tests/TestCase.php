<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as FrameworkTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
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
            ->withRouting(using: static function (): void {
                Route::match(['GET', 'HEAD'], '/uploads/{path}', PublicMediaController::class)->where('path', '.*');
            })->create();
        $app->afterBootstrapping(LoadConfiguration::class, static function (Application $app): void {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
                'cache.default' => 'array', 'session.driver' => 'array',
                'logging.default' => 'null', 'logging.channels.null' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class],
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }
}
