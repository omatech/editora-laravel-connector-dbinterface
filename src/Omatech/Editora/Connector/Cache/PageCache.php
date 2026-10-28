<?php

namespace Omatech\Editora\Connector\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves /{language} and /{language}/{slug} pages to every visitor from one stored copy per
 * CacheVersion, for at most editora-cache.page.ttl seconds. A page is stored only when another visitor
 * may receive it: a complete 200 HTML document without the visitor's CSRF token, a nonce or a
 * VarDumper dump, rendered for a session that holds nothing but language and navigation, and adding no
 * header or cookie the copy would lose. Register it as global middleware after any middleware whose
 * headers every response needs.
 *
 * Pages rendered from content that was not fresh are answered as STALE and not stored, and the key
 * includes the last publication, so a publication reaches visitors once its content has refreshed. It
 * also includes the build, so a deploy never replays pages rendered by the previous code.
 *
 * Editora admin sessions never get or leave the shared copy: their pages carry the edit tools
 * (@editora_scripts). The session is only known once the application has run, so every response to one
 * sets EDITOR_COOKIE, and requests carrying it skip the cache until the session no longer has a role.
 *
 * Responses carry no-store: an edge cache would otherwise keep pages that a new version no longer
 * reaches. Stored bytes per window are capped because the store may be a Valkey that several
 * applications share and that does not evict.
 */
final class PageCache
{
    private const HTML = '#^text/html(?:;[ -~]*)?$#iD';
    private const UNSHARABLE = '/\bname\s*=\s*["\']?(?:_token|csrf[-_]?token)\b|<meta\s+name\s*=\s*["\']csrf-token|\bnonce\s*=\s*["\'][^"\']+|\bsf-dump\b/i';
    private const ANONYMOUS_SESSION = ['_token', '_previous', '_flash', 'locale', 'u_lang'];
    private const RENDERED_HEADERS = ['cache-control', 'content-type', 'date', 'set-cookie'];

    /** Marks a browser with an Editora admin session; ContentFreshness can read publications again for it. */
    public const EDITOR_COOKIE = 'editora_editor';

    public function __construct(private CacheVersion $version, private ContentFreshness $freshness)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $request->cookies->has(self::EDITOR_COOKIE)
            ? $this->mark($next($request), 'BYPASS')
            : $this->serve($request, $next);

        return $this->markEditor($request, $response);
    }

    private function serve(Request $request, Closure $next): Response
    {
        if (!$this->eligible($request)) {
            return $this->mark($next($request), 'BYPASS');
        }
        try {
            $store = Cache::store(config('editora-cache.page.store'));
            $key = $this->key($request);
            $entry = $store->get($key);
        } catch (Throwable $e) {
            report($e);
            return $this->mark($next($request), 'BYPASS');
        }
        if (is_array($entry)) {
            return $this->replay($request, ...$entry);
        }
        $response = $next($request);
        if ($this->freshness->stale()) {
            return $this->unshared($this->mark($response, 'STALE'));
        }
        if ($this->storable($request, $response)) {
            $this->store($store, $key, $response);
        }

        return $this->unshared($this->mark($response, 'MISS'));
    }

    /** Sets or renews EDITOR_COOKIE for an admin session, like the session itself, and removes it once the role is gone. */
    private function markEditor(Request $request, Response $response): Response
    {
        // A page replayed from the cache never started a session: nothing is known about the visitor.
        if (!$request->hasSession()) {
            return $response;
        }
        if ((string) $request->session()->get('rol_id', '') !== '') {
            $response->headers->setCookie(cookie(self::EDITOR_COOKIE, '1', (int) config('session.lifetime'),
                config('session.path'), config('session.domain'), config('session.secure'), true, false, config('session.same_site')));
        } elseif ($request->cookies->has(self::EDITOR_COOKIE)) {
            $response->headers->setCookie(cookie()->forget(self::EDITOR_COOKIE, config('session.path'), config('session.domain')));
        }

        return $response;
    }

    private function eligible(Request $request): bool
    {
        if (!config('editora-cache.page.enabled') || !$request->isMethodCacheable() || $request->getQueryString() !== null) {
            return false;
        }
        $languages = implode('|', array_map(fn (string $language) => preg_quote($language, '~'), config('editora.availableLanguages')));

        return preg_match('~^/(?:'.$languages.')(?:/[a-z0-9_-]+)?/?$~D', $request->getPathInfo()) === 1;
    }

    private function key(Request $request): string
    {
        return 'page:v1:'.hash('sha256', json_encode(
            [$request->getSchemeAndHttpHost(), '/'.$request->path(), $this->version->current(), $this->freshness->lastPublication(), $this->build()],
            JSON_THROW_ON_ERROR
        ));
    }

    /** Every build caches the configuration anew; without a cached configuration there is no build to tell apart. */
    private function build(): int
    {
        $config = app()->getCachedConfigPath();

        return is_file($config) ? (int) filemtime($config) : 0;
    }

    private function replay(Request $request, string $type, string $body): Response
    {
        $response = new HtmlResponse($body, 200, ['Content-Type' => $type]);

        return $this->unshared($this->mark($response->prepare($request), 'HIT'));
    }

    private function storable(Request $request, Response $response): bool
    {
        return $response->getStatusCode() === 200
            && preg_match(self::HTML, (string) $response->headers->get('Content-Type')) === 1
            && $this->onlyRenderedHeaders($request, $response)
            && $this->anonymousSession($request)
            && $this->sharable($request, (string) $response->getContent());
    }

    private function onlyRenderedHeaders(Request $request, Response $response): bool
    {
        $session = $request->hasSession() ? [$request->session()->getName(), $request->session()->getId()] : [];
        foreach ($response->headers->getCookies() as $cookie) {
            if (!in_array($cookie->getName(), [...$session, 'XSRF-TOKEN'], true)) {
                return false;
            }
        }

        return array_diff(array_keys($response->headers->all()), self::RENDERED_HEADERS) === [];
    }

    private function anonymousSession(Request $request): bool
    {
        if (!$request->hasSession()) {
            return true;
        }
        $session = $request->session();

        return array_diff(array_keys($session->all()), self::ANONYMOUS_SESSION) === []
            && $session->get('_flash.new', []) === [] && $session->get('_flash.old', []) === [];
    }

    private function sharable(Request $request, string $body): bool
    {
        $token = $request->hasSession() ? $request->session()->token() : null;

        return strlen($body) <= config('editora-cache.page.max_bytes') && str_contains($body, '</html>')
            && !preg_match(self::UNSHARABLE, $body) && ($token === null || !str_contains($body, $token));
    }

    private function store(Repository $store, string $key, Response $response): void
    {
        $entry = [(string) $response->headers->get('Content-Type'), (string) $response->getContent()];
        $ttl = (int) config('editora-cache.page.ttl');
        $window = 'page-budget:'.intdiv(time(), $ttl);
        try {
            $store->add($window, 0, 2 * $ttl);
            if ($store->increment($window, strlen(implode('', $entry))) <= config('editora-cache.page.budget_bytes')) {
                $store->put($key, $entry, $ttl);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function mark(Response $response, string $state): Response
    {
        if (preg_match(self::HTML, (string) $response->headers->get('Content-Type'))) {
            $response->headers->set('X-Page-Cache', $state);
        }

        return $response;
    }

    private function unshared(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Thu, 19 Nov 1981 08:52:00 GMT');

        return $response;
    }
}
