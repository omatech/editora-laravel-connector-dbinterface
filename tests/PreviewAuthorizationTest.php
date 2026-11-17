<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Omatech\Editora\Connector\EditoraController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Fixtures\PreviewLookup;

final class PreviewAuthorizationTest extends TestCase
{
    private PreviewLookup $lookup;

    protected function setUp(): void
    {
        parent::setUp();
        // PHPUnit runs in CLI, where the connector intentionally skips its HTTP constructor.
        new \ReflectionProperty(Application::class, 'isRunningInConsole')->setValue($this->app, false);
        $this->lookup = new PreviewLookup();
        $this->app->instance('Utils', $this->lookup);
        config(['editora.availableLanguages' => ['ca'], 'editora.defaultLanguage' => 'ca',
            'editora.forcedLanguage' => '', 'editora.ignoreBrowserLanguage' => true,
            'editora.controllersNamespace' => 'Tests\\Fixtures\\',
            'editora.notFoundHttpException' => NotFoundHttpException::class]);
        Route::middleware('web')->get('/{language}/{nice_url}', [EditoraController::class, 'init']);
    }

    public function test_preview_authorization_precedes_url_resolution(): void
    {
        $request = Request::create('/ca/pending?req_info=1');
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
        try {
            new EditoraController($request);
            self::fail('Anonymous preview resolved a pending page before authorization');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame(0, $this->lookup->lookups);
            self::assertFalse($this->lookup->preview);
        }
    }
    public function test_anonymous_preview_is_an_http_404_without_resolving_content(): void
    {
        $this->get('/ca/pending?req_info=1')->assertNotFound();
        self::assertSame(0, $this->lookup->lookups);
        self::assertFalse($this->lookup->preview);
    }

    public function test_an_editor_can_preview_pending_content(): void
    {
        $this->withSession(['user_id' => 7])->get('/ca/pending?req_info=1')
            ->assertOk()->assertJson(['preview' => true, 'id' => 42]);
        self::assertSame(1, $this->lookup->lookups);
    }

    public function test_a_session_alone_does_not_enable_preview(): void
    {
        $this->withSession(['user_id' => 7])->get('/ca/pending')->assertNotFound();
        self::assertFalse($this->lookup->preview);
    }

    public function test_an_editor_marker_cookie_does_not_authorize_preview(): void
    {
        $this->withCookie('editora_editor', '1')->get('/ca/pending?req_info=1')->assertNotFound();
        self::assertSame(0, $this->lookup->lookups);
    }

    public function test_normal_published_content_is_still_public(): void
    {
        $this->get('/ca/published?req_info=0')->assertOk()->assertJson(['preview' => false]);
    }

    public function test_public_pending_content_still_returns_404(): void
    {
        $this->get('/ca/pending')->assertNotFound();
        self::assertFalse($this->lookup->preview);
    }

}
