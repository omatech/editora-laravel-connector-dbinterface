<?php

namespace Tests;

use Tests\Fixtures\PreviewLookup;

final class PreviewAuthorizationTest extends TestCase
{
    private PreviewLookup $lookup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparePreviewRoutes();
        $this->lookup = new PreviewLookup();
        $this->app->instance('Utils', $this->lookup);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_anonymous_preview_is_rejected_before_rendering(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->get('/ca/pending?req_info=1');
    }

    public function test_anonymous_preview_is_an_http_404(): void
    {
        $this->get('/ca/pending?req_info=1')->assertNotFound();
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

    public function test_the_session_cookie_is_loaded_before_preview_authorization(): void
    {
        $folder = storage_path('framework/sessions');
        mkdir($folder, 0700, true);
        config(['session.driver' => 'file', 'session.files' => $folder]);
        $session = app('session')->driver();
        $session->start();
        $session->put('user_id', 7);
        $id = $session->getId();
        $session->save();
        app('session')->forgetDrivers();

        $this->withCookie(config('session.cookie'), $id)->get('/ca/pending?req_info=1')
            ->assertOk()->assertJson(['preview' => true, 'id' => 42]);
    }
}
