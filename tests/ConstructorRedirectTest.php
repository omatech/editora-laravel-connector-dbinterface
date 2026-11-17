<?php

namespace Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Omatech\Editora\Connector\EditoraController;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Fixtures\RedirectHeaders;

// The old send()+die ends the process. A separate process makes that failure observable
// without letting it terminate the rest of the suite.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ConstructorRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->preparePreviewRoutes();
        config(['editora.homeNiceUrl' => false]);
        $this->app->instance('Utils', new class {
            public function getInstanceLink(int $id, string $language): string
            {
                return '/'.$language.'/front-page';
            }
        });
        $this->app->make(Kernel::class)->pushMiddleware(RedirectHeaders::class);
        Route::middleware('web')->match(['GET', 'HEAD'], '/', [EditoraController::class, 'init']);
    }

    public function test_the_language_redirect_passes_through_global_response_middleware(): void
    {
        $this->get('/')->assertStatus(301)->assertRedirect('/ca/')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_redirect_retains_the_query_string(): void
    {
        $this->get('/?campaign=synthetic')->assertStatus(301)->assertRedirect('/ca/?campaign=synthetic')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_head_request_keeps_the_redirect_and_response_policy(): void
    {
        $this->head('/')->assertStatus(301)->assertRedirect('/ca/')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_named_home_redirect_also_returns_through_the_kernel(): void
    {
        config(['editora.homeNiceUrl' => true]);
        $this->get('/')->assertStatus(301)->assertRedirect('/ca/front-page')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
