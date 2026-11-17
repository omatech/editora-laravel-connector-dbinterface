<?php declare(strict_types=1);

namespace Tests;

use Aws\Api\DateTimeResult;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response as StorageResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicMediaTest extends TestCase
{
    private const string ETAG = '"d4a382b5a16d18f6e5c69553789cc568"';

    /** @var list<CommandInterface> */
    private array $commands = [];

    private MockHandler $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new MockHandler();
        config(['filesystems.cloud_media' => true, 'filesystems.disks.editora_public' => [
            'driver' => 's3', 'bucket' => 'bucket-fixture', 'root' => 'public', 'region' => 'auto',
            'endpoint' => 'https://storage.example.invalid', 'use_path_style_endpoint' => true,
            'key' => 'FAKE_MEDIA_KEY', 'secret' => 'fake-secret-never-valid', 'handler' => $this->storage,
        ]]);
        config(['editora-media.denied_prefixes' => ['private', 'applications', 'issues']]);
        Storage::forgetDisk('editora_public');
    }

    public function test_media_is_served_by_the_site_with_cache_headers_and_no_bucket_url(): void
    {
        $this->storedObject('PNG-BYTES', 'application/octet-stream');
        $response = $this->get('/uploads/20220426/captura%20m%C3%B3vil.png?Key=documents/other.pdf&response-content-disposition=attachment');

        $response->assertOk();
        self::assertSame('PNG-BYTES', $response->streamedContent());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame('9', $response->headers->get('Content-Length'));
        self::assertSame(self::ETAG, $response->headers->get('ETag'));
        self::assertSame('Mon, 26 Oct 2026 16:21:00 GMT', $response->headers->get('Last-Modified'));
        self::assertStringContainsString('public', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        self::assertSame(['nosniff'], $response->headers->all('X-Content-Type-Options'));
        self::assertFalse($response->headers->has('Location'));
        // Cookies would stop the CDN from keeping the file and open a session per image.
        self::assertSame([], $response->headers->getCookies());
        self::assertSame('GetObject', $this->commands[0]->getName());
        self::assertSame('bucket-fixture', $this->commands[0]['Bucket']);
        self::assertSame('public/uploads/20220426/captura móvil.png', $this->commands[0]['Key']);
        self::assertTrue($this->commands[0]['@http']['stream']);
        self::assertNull($this->commands[0]['Range']);
    }

    public function test_head_reads_metadata_without_downloading_the_object(): void
    {
        $this->storedObject('', 'image/png', ['ContentLength' => 15]);
        $response = $this->head('/uploads/20220426/canary.png');

        $response->assertOk();
        self::assertSame('HeadObject', $this->commands[0]->getName());
        self::assertSame('15', $response->headers->get('Content-Length'));
        self::assertSame('', $response->getContent());
    }

    public function test_a_matching_etag_is_answered_with_not_modified(): void
    {
        $this->storedObject('PNG-BYTES');
        $response = $this->get('/uploads/20220426/canary.png', ['If-None-Match' => self::ETAG]);

        $response->assertStatus(304);
        self::assertSame('', $response->streamedContent());
    }

    public function test_a_single_byte_range_is_forwarded_for_video_playback(): void
    {
        $this->storedObject('0123', 'video/mp4', ['ContentRange' => 'bytes 0-3/10']);
        $response = $this->get('/uploads/20220426/reel.mp4', ['Range' => 'bytes=0-3']);

        $response->assertStatus(206);
        self::assertSame('0123', $response->streamedContent());
        self::assertSame('bytes 0-3/10', $response->headers->get('Content-Range'));
        self::assertSame('video/mp4', $response->headers->get('Content-Type'));
        self::assertSame('bytes=0-3', $this->commands[0]['Range']);
    }

    public function test_an_svg_opened_directly_cannot_run_scripts_on_the_site(): void
    {
        $this->storedObject('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.domain)</script></svg>', 'text/html');
        $response = $this->get('/uploads/20220426/logo.svg');

        $response->assertOk();
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        self::assertStringContainsString('sandbox', implode(' ', $response->headers->all('Content-Security-Policy')));
        self::assertSame(['nosniff'], $response->headers->all('X-Content-Type-Options'));
    }

    public function test_missing_media_is_not_found_without_details(): void
    {
        $this->storageError(404, 'NoSuchKey');
        $response = $this->get('/uploads/20220426/missing.png');

        $response->assertNotFound();
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertSame('', $response->getContent());
    }

    public function test_storage_failure_is_reported_and_not_cached(): void
    {
        Exceptions::fake();
        $this->storageError(403, 'AccessDenied');
        $response = $this->get('/uploads/20220426/canary.png');

        $response->assertStatus(502);
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Exceptions::assertReported(S3Exception::class);
    }

    public function test_private_folders_and_executable_files_never_reach_storage(): void
    {
        $paths = ['private/canary.png', 'applications/cv.pdf', 'Issues/capture.png', 'canary.php', '.hidden.png', 'folder%5Ccanary.png'];
        foreach ($paths as $path) {
            $answer = $this->getJson('/uploads/'.$path);
            $answer->assertNotFound();
            self::assertStringContainsString('no-store', $answer->headers->get('Cache-Control'));
        }
        self::assertSame([], $this->commands);
    }

    public function test_local_media_is_left_to_the_web_server(): void
    {
        config(['filesystems.disks.editora_public' => ['driver' => 'local', 'root' => public_path()]]);
        Storage::forgetDisk('editora_public');
        $answer = $this->getJson('/uploads/20220426/canary.png');
        $answer->assertNotFound();
        self::assertStringContainsString('no-store', $answer->headers->get('Cache-Control'));
        self::assertSame([], $this->commands);
    }

    private function storedObject(string $bytes, string $storedType = 'image/png', array $metadata = []): void
    {
        $this->storage->append(function (CommandInterface $command) use ($bytes, $storedType, $metadata) {
            $this->commands[] = $command;

            return new Result($metadata + [
                'Body' => Utils::streamFor($bytes), 'ContentLength' => strlen($bytes), 'ContentType' => $storedType,
                'ETag' => self::ETAG, 'LastModified' => new DateTimeResult('2026-10-26T16:21:00Z'),
            ]);
        });
    }

    private function storageError(int $status, string $code): void
    {
        $this->storage->append(function (CommandInterface $command) use ($status, $code) {
            $this->commands[] = $command;

            return new S3Exception($code, $command, ['code' => $code, 'response' => new StorageResponse($status)]);
        });
    }
}
