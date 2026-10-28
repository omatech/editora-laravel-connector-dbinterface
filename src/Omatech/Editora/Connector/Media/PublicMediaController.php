<?php

namespace Omatech\Editora\Connector\Media;

use Aws\ResultInterface;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Closure;
use Illuminate\Http\Request;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves CMS media from a private S3 bucket under the site's own URL, so the bucket host never reaches
 * the browser, and lets browsers and the CDN keep each file for editora-media.cache_seconds. Route it
 * as GET /uploads/{path} with path matching '.*'. Without a complete HTTPS S3 disk it answers 404:
 * local media is served by the web server. The S3 client is built from the disk configuration, so the
 * application needs aws/aws-sdk-php but not the Flysystem S3 adapter.
 */
final class PublicMediaController
{
    /** An SVG opened directly is a document of this site: the sandbox keeps any script in it inert. */
    private const SVG_POLICY = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox";

    public function __invoke(Request $request, string $path): Response
    {
        $disk = $this->disk();
        if ($disk === null || !PublicMedia::allows($path)) {
            return response('', 404);
        }
        try {
            $object = $this->fetch($disk, $request, $path);
        } catch (S3Exception $e) {
            return $this->storageFailure($e);
        }
        $response = $request->isMethod('HEAD') ? response('') : response()->stream($this->copy($object['Body']));
        $response->setStatusCode(isset($object['ContentRange']) ? 206 : 200);
        $response->headers->add($this->headers($path, $object));
        $response->setPublic()->setMaxAge((int) config('editora-media.cache_seconds'))->setLastModified($object['LastModified']);
        $response->isNotModified($request);

        return $response;
    }

    private function disk(): ?array
    {
        $config = config('filesystems.disks.'.config('editora-media.disk'));

        return is_array($config) && $this->completeHttpsS3($config) ? $config : null;
    }

    private function completeHttpsS3(array $config): bool
    {
        $endpoint = parse_url((string) ($config['endpoint'] ?? 'https://s3.amazonaws.com'));

        return ($config['driver'] ?? null) === 's3' && !empty($config['bucket']) && !empty($config['key']) && !empty($config['secret'])
            && ($endpoint['scheme'] ?? null) === 'https' && !empty($endpoint['host'])
            && !isset($endpoint['user']) && !isset($endpoint['query']) && !isset($endpoint['fragment']);
    }

    private function fetch(array $disk, Request $request, string $path): ResultInterface
    {
        $client = new S3Client(['version' => 'latest', 'credentials' => ['key' => $disk['key'], 'secret' => $disk['secret']]] + $disk);
        $key = ltrim(trim((string) ($disk['root'] ?? ''), '/').'/'.PublicMedia::key($path), '/');
        $args = ['Bucket' => $disk['bucket'], 'Key' => $key];
        if ($request->isMethod('HEAD')) {
            return $client->headObject($args);
        }
        $range = (string) $request->headers->get('Range');
        if (preg_match('/^bytes=(\d+-\d*|-\d+)$/D', $range)) {
            $args['Range'] = $range;
        }

        return $client->getObject($args + ['@http' => ['stream' => true]]);
    }

    private function headers(string $path, ResultInterface $object): array
    {
        $type = PublicMedia::type($path);

        return array_filter([
            'Content-Type' => $type ?? 'application/octet-stream',
            'Content-Disposition' => $type === null ? 'attachment' : null,
            'Content-Length' => (string) $object['ContentLength'],
            'Content-Range' => $object['ContentRange'],
            'Content-Security-Policy' => PublicMedia::extension($path) === 'svg' ? self::SVG_POLICY : null,
            'ETag' => $object['ETag'],
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
        ], fn ($value) => $value !== null);
    }

    private function copy(StreamInterface $body): Closure
    {
        return static function () use ($body): void {
            while (!$body->eof()) {
                echo $body->read(1048576);
                flush();
            }
        };
    }

    private function storageFailure(S3Exception $e): Response
    {
        if (in_array($e->getStatusCode(), [404, 416], true)) {
            return response('', $e->getStatusCode());
        }
        report($e);

        return response('', 502)->header('Cache-Control', 'no-store');
    }
}
