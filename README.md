# Installation of a new frontend project using editora-laravel-connector-dbinterface

## Laravel Setup

### Create the new laravel empty project

```
composer create-project --prefer-dist laravel/laravel editora-test
```

### Setup you .env file with database connection and so on

### In the composer.json file add the stability settings in the root of the file and change the name and the description of the project, for example:

```
    "name": "editora-test",
    "description": "Editora Frontend",
	"minimum-stability": "dev",
	"prefer-stable": true,
```

### Do a composer update

```
composer update
```

### Publish the new vendor, run: 

```
php artisan vendor:publish --provider=Omatech\Editora\Connector\ConnectorServiceProvider
```

### Put all your markup assets in resources/assets/markup (scss, js, img, fonts,...)

### Overwrite the mix.js call in webpack.mix.js in root folder for this one 

```
mix.js('resources/assets/js/app.js', 'public/js')
    .sass('resources/assets/markup/scss/styles.scss', 'public/css/styles.css')
    .copy('resources/assets/markup/js', 'public/js')
    .copy('resources/assets/markup/img', 'public/img')
    .copy('resources/assets/markup/fonts', 'public/fonts')
    .version();
```

### Install and run npm

```
npm install
npm run dev
```

### Add editora routes in routes/web.php
```
use Omatech\Editora\Connector\Editora;

Editora::routes();
```

### In config folder there're the two files needed for editora setup editora.php sets the language and different editora options editoradatabase.php sets the editora structure 

### Modify editoradatabase.php and apply changes running

```
php artisan editora:create
```

### Check if editoradatabase is changed

```
php artisan editora:databasechanged
```

### Generate fake content for editora

```
php artisan editora:fakecontent --delete_previous_data
```

### Create the MVC structure for the current Editora Structure

```
php artisan editora:createmvc
```

### Clone the editora_admin in another folder, for example editora-test-admin
```
cd .. (to your www root folder)
mkdir editora-test-admin
cd editora-test-admin

git clone https://aponsoma@bitbucket.org/omatechteam/editora_admin.git .
```

### Copy the config and change the database and folders and urls settings in ompinfo.php (look for # characters to see what to change)
```
cd conf
cp ompinfo_sample.php ompinfo.php
```

### Double check the HASHED_PASSWORDS, must be 1


### Change your virtual hosts to include the admin directory and restart Apache, for example

```
<VirtualHost *:80>
	DocumentRoot "/var/www/editora-test/public"
	ServerName editoratest.localhost
	<Directory "/var/www/editora-test/public">
		Allow from all
		Require all granted
		AllowOverride All
	</Directory>   

	Alias /admin "/var/www/editora-test-admin"
	<Directory "/var/www/editora-test-admin">
		AllowOverride All
		Allow from all
		Require all granted

	</Directory>
</VirtualHost>
```

# Laravel Commands

## Generator
Creates the Editora database structure following the rules set in config/editoradatabase.php

```
php artisan editora:create
```

## Fake Content
Creates random content for the Editora database.

```
php artisan editora:fakecontent
```

### Arguments

```
php artisan editora:fakecontent --exclude_classes=1,10,11,12,13

{--num_instances=} {--include_classes=} {--exclude_classes=} {--pictures_theme=} {—debug} {--delete_previous_data}

--num_instances number of instance to create for each class
--include_classes generate only this class_ids, comma separated
--exclude_classes generate all but this class_ids, comma separated
--pictures_theme generate pictures themed with that word, default:cats you can use abstract, animals, business, cats, city, food, nightlife, fashion, people, nature, sports, technics, transport
--debug show all sqls (if not present false)
--delete_previous_data **USE WITH CAUTION**, if set deletes all the previous data before generating the fake data
```

**TBD**
Falta añadir contenido aleatorio para algunos atributos (mapas, date,...).


## CreateMVC
Create the Model, View and Controller files for the Frontend (if they don't exists)

```
php artisan editora:createmvc

--include_classes generate only this class_ids, comma separated
--exclude_classes generate all but this class_ids, comma separated
--old_school_controllers allow generation of old_school_controllers instead of new ones
```

### Arguments

```
--include_classes=1,2,3 generate only this class_ids, comma separated
--force_overwrite_views
--force_overwrite_models
--force_overwrite_controllers
--force_overwrite_all
```

**TBD**
Falta crear argumento de force para borrar si o si las clases.


## Modernize
Improves database structure of the editora database, use only in old editoras, not new projects. It creates indexes, new columns added recently like batch_id, external_id and changes to use encrypted passwords

```
php artisan editora:modernize
```









# Caching and private media

Optional building blocks, all off until the application registers them. The provider merges their defaults as `editora-cache` and `editora-media`; an application `config/editora-cache.php` or `config/editora-media.php` replaces each top-level key it defines.

## Content cache

`Cache\CachedExtractor` caches the complete graph returned by the listed extractor methods. An entry is fresh for `CMS_CACHE_TTL` seconds (60) and, up to `CMS_CACHE_STALE_TTL` (86400, a day), is served at once and refreshed after the response. Nested calls, preview, debug and `avoid_cache` always read the database. Register it in a service provider's `boot()`:

```php
\Omatech\Editora\Connector\Cache\CachedExtractor::register($this->app, ['findInstanceByIdAllElements', 'findChildrenInstances']);
```

Leave out methods whose arguments come from free text, such as a search building `findInstancesInList` ids: every distinct query would become a cache entry. `Cache\CachedStaticTexts::register($this->app)` does the same for static texts, once per language.

`CMS_CACHE_STORE` selects the store (default: the application's), `CMS_CACHE_ENABLED=false` turns it off and changing `CMS_CACHE_VERSION` stops serving every older copy. To discard everything on publication instead, bind `Cache\CacheVersion` to your own implementation.

## Publications

When the Editora admin runs inside the application, `Cache\ContentFreshness::register($this->app)` (in `register()`, before `CachedExtractor::register`) makes publications reach visitors without a cold render. Every write the admin makes to public CMS tables records a publication time in the content store; sign-in, favourites, recent items, roles and permissions do not count, nor do writes from visitor requests or console commands. Content read from the database before that time is no longer fresh: it is served once more, at once, and refreshed after the response. With `CMS_CACHE_PUBLICATIONS=reload` (or `'publications' => 'reload'` in the application's `config/editora-cache.php`) it is read again before it is served instead: the first visit after a publication already shows it, at the cost of reading that page's content from the database. `editors` does that only for browsers with an Editora admin session (marked by `PageCache`, below): the editor sees a save on the first visit and that read brings the content up to date for everyone, while a visitor who arrives first gets the previous copy once and never waits. All admin writes go through the Laravel connection, so saves, publication, deletion, cloning, relations, ordering, static texts, images and imports are all seen.

Static texts cached by `Cache\CachedStaticTexts` follow the same rule. When the admin runs elsewhere and notifies the application, as Aptima's integration channel does, bind `ContentFreshness` with the content store instead and call `published()` on each notification; it throws when the publication cannot be recorded, so the notification can fail visibly.

### Publishing dates

Editora shows an object from its `publishing_begins` and hides it from its `publishing_ends`, compared with the database's `NOW()`, so public content changes at those times without any write. When `ContentFreshness` has a store (as `register()` gives it) and the application's default connection is MySQL or MariaDB, the last such date of a published object (status `O`) counts as a publication at that second, and content read up to it is read again before it is served, in every `publications` mode: an object leaves or enters the site in the second of its date, never from a copy cached before it. `PageCache` keeps no page past the next date and stores none while the dates cannot be read (the failure is reported). The dates are read with one query, measured by the database clock, and kept in the content store until the next one passes, the admin publishes or 60 seconds go by, so dates written without a publication, such as by a console import, are seen within a minute; every other request reads them together with the last publication, in the same round trip to the store. A `ContentFreshness` without a store, such as the connector's default binding, does not follow dates.

Editors type those dates in their own time and the admin stores them with no zone, while Laravel Cloud's MySQL runs in UTC: compared with a UTC `NOW()`, an object would appear and end an hour late (two in summer). The connector therefore opens every MySQL or MariaDB session in the editors' zone, `editora.databaseTimezone` (`EDITORA_DATABASE_TIMEZONE`, `Europe/Madrid` by default; empty keeps the server's zone): in `register()`, before any connection opens, it sets each such Laravel connection's `timezone` to the offset of that moment (`+01:00`, `+02:00` in summer), and the Doctrine connections of the extractor, `Utils` and `Loader` run the same `SET time_zone` as they open. An offset, not the zone's name, because MySQL servers without time zone tables (the official image) refuse names; each request computes it anew, so the clock change is followed, while a long-running worker keeps the offset it started with. `TIMESTAMP` columns are read and written in the session zone too, so rows written before read shifted by the difference; Editora's own dates are `DATETIME` and are not converted.

## Page cache

`Cache\PageCache` is a global middleware that serves `/{language}` and `/{language}/{slug}` to every visitor from one copy per cache version, for `PAGE_CACHE_TTL` seconds (3600, an hour) and never past the next publishing date. The key includes the build (the time the configuration was cached), so every deploy starts afresh, and the last publication, and a page rendered from content that was not fresh is answered as `STALE` and not stored, so a page shows a publication as soon as its content has refreshed. The TTL only bounds how long an unchanged page is kept: what the Editora admin writes, the publishing dates, `CMS_CACHE_VERSION` and deploys reach a page through its key. Anything else a page shows waits up to the TTL, such as rows written outside the Editora admin (indexers, console imports, queued jobs, other applications), a `ContentFreshness` without a store, or a clock read by the page itself: raise `PAGE_CACHE_TTL` only for a site whose pages show nothing of the kind. It only stores a page that another visitor may receive: a complete `200 text/html` document of at most 512 KiB, without the session's CSRF token, `_token` fields, a `csrf-token` meta, a `nonce` or a VarDumper dump, rendered for a session holding only language and navigation, and adding no header or cookie besides the session ones. Answers carry `X-Page-Cache: HIT|MISS|STALE|BYPASS` and `Cache-Control: no-store`, so an edge cache never keeps a page the version no longer reaches. Pages are limited (what may be shared, `max_bytes` each, the TTL and the publishing dates), not the bytes of the store: the Valkey behind it autoscales. Register it after any middleware whose headers every response needs:

```php
protected $middleware = [
    // ...
    \App\Http\Middleware\SecurityHeaders::class,
    \Omatech\Editora\Connector\Cache\PageCache::class,
];
```

Editora admin sessions never get or leave the shared copy, because their pages carry the edit tools (`@editora_scripts`). The session is only known after the application has run, so every response to a session with a role sets the `editora_editor` cookie (renewed like the session, removed once the role is gone), and requests carrying it skip the cache.

`PAGE_CACHE_STORE` selects the store and `PAGE_CACHE_ENABLED=false` turns it off. Pages are limited, not the store: a page over `editora-cache.page.max_bytes` (512 KB) is not stored, and there is no limit on the bytes stored, because the Valkey behind it autoscales.

## Warming the caches

`php artisan editora:warm` visits, through the site's own URL, the published pages that the page cache holds no copy of, so no visitor pays for an empty cache. It lists `/{language}` for each language in `editora.availableLanguages` (or the home instance's nice URL when `homeNiceUrl` is true), then `/{language}/{nice_url}` for every instance that is published now (status `O`, inside its publishing dates, compared on the database connection's clock) and is a page: an instance whose class has no page controller (see `EditoraController::pageController()`) answers 404 and is skipped. A page whose copy `PageCache` holds under the current key is skipped without a request (`PageCache::stored()` computes the key from the URL), so a pass visits only what is missing.

Dated publications are warmed only while they are recent. List their class tags (`omp_classes.tag`) in `config/editora.php`, and the warm visits only those published in the last `editora-cache.warm.recent_months` months (3); an older one is still cached when someone visits it:

```php
// config/editora.php
'publicationClasses' => ['News', 'Post'],
```

Without `publicationClasses` every page is warmed, whatever its date. Years of news can take most of each pass and of the page cache for pages that are rarely read. An empty content cache is filled, and so is content past its TTL: a pass finds most content older than `CMS_CACHE_TTL`, and a visitor would get it at once and leave the page unstored (`STALE`), so the warm's requests carry `X-Editora-Warm` with a token derived from `APP_KEY` (`ContentFreshness::warmToken()`) and read it again before the answer. Pages that `PageCache` may share are stored. A request without that token never waits for such a read. It reports how many pages had a copy and how many it visited, with their status and `X-Page-Cache` counts (`4 pages in 1.2 s. Status: 200: 1. Page cache: MISS: 1. Already stored: 3. Visited: 1.`), names every page that answered 4xx, 5xx or nothing, and fails when any answered 5xx or nothing.

`--url=` visits another base URL (default `APP_URL`); `CMS_CACHE_WARM_TIMEOUT` (60 s) bounds each request. Pages are visited one after another, so a run never adds more than one page render at a time to the site.

With `--if-changed` it lists and visits nothing while the pages are as the last complete pass left them: it compares the build, `CMS_CACHE_VERSION` and the last publication or publishing date passed (`PageCache::generation()`, read without a request or a query of the pages) with what that pass recorded in the page store, and ends with `Unchanged since the last complete pass: no page visited.` A deploy, an admin publication or a publishing date that passes starts a pass, which says what changed and skips the pages that still have a copy. A pass is complete, and records that state, when no page answered 5xx or nothing and none answered `STALE`, as content read in the very second of a publication leaves it; otherwise the next run visits what is still missing. The record lasts the page TTL, so a pass also runs once the copies it stored may have expired. A page `PageCache` never stores, such as one with a CSRF token, is visited by every pass. A plain `editora:warm` checks the copies the same way and records the state too.

Schedule `--if-changed` every minute, so a publication or a deploy is warm within a minute or two, and a full pass twice a day for whatever else has no copy (pages that visitors stored and that expired, a pass that failed):

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('editora:warm --if-changed')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('editora:warm')->twiceDaily()->withoutOverlapping()->runInBackground();
```

Add `->onOneServer()` to both when more than one server runs the scheduler (it needs a cache store with locks, such as Valkey). Skipping relies on the warm computing the key the site computes: the scheme and host of `APP_URL` (or `--url`) must be those the site sees (behind a proxy, trust it, so a page requested over HTTPS is keyed as HTTPS), and the scheduler must run the same build, with the same cached configuration. A visited page answering `HIT` means they differ.

## Media from a private bucket

`Media\PublicMediaController` serves CMS media from a private S3 bucket under the site's own URL, so the bucket host never reaches the browser:

```php
Route::get('uploads/{path}', \Omatech\Editora\Connector\Media\PublicMediaController::class)->where('path', '.*');
```

It reads `editora-media.prefix` + path from the `editora-media.disk` filesystem disk, which must be a complete `s3` disk with an HTTPS endpoint; otherwise it answers 404 and the web server serves local media. It needs `aws/aws-sdk-php`, not the Flysystem S3 adapter. Responses are streamed with `Cache-Control: public, max-age=86400` (`cache_seconds`), `ETag`, `Last-Modified`, 304 answers and a single forwarded `bytes=` range. The content type comes from `editora-media.types`, never from stored metadata; every answer carries `nosniff` and SVG adds a sandbox CSP. Other extensions answer 404, or download as attachments with `attachments => true`. Hidden paths, traversal, backslashes, `denied_prefixes` and `denied_extensions` answer 404 without reaching storage; every 404 (including an unavailable disk or a rejected path) carries `Cache-Control: no-store`, so a file uploaded later can be served immediately; storage failures answer 502 without caching and are reported.

## Package checks

Run `make build install check mysql-check` to build the pinned PHP 8.5.12 test image, install development dependencies and run lint, the standalone Laravel HTTP tests, Composer validation and the dependency audit. `make test ARGS="--filter PublicMediaTest"` runs the media contracts. Tests use generated applications in temporary directories, in-memory SQLite and synthetic S3 responses; they need no project credentials or external storage. The MySQL check uses the pinned MySQL 8.4 image, an internal network and synthetic tables in a disposable database; it verifies real URL resolution for published, pending, future and expired pages, that cached content and cached pages follow publishing dates to the second, and that `editora:warm --if-changed` visits pages only after a publication, a publishing date or a deploy, with Carbon's clock and the database's (`SET TIMESTAMP`) moved together. It refuses to reuse an active test stack and removes its services when it finishes. The same command is configured in `.github/workflows/tests.yml`.

`_fileInfo($path)` describes a public CMS upload such as `/uploads/document.pdf` using the same `editora-media.disk` and prefix as the media controller. It reads the filesystem size metadata, so no local copy or object download is needed; an empty file shows `0.00B` and a missing or disallowed public path returns an empty string. Stored file names are used as raw keys, including literal percent signs.

## Preview requests

`?req_info=1` enables preview only when the browser has the existing Editora `user_id` session. Authorization runs before rendering, after web middleware has loaded the session; an anonymous preview request receives an HTTP 404 instead of ending with a plain-text 200 response. An editor marker cookie alone does not authorize preview. Requests without this flag use public publication rules, including for signed-in editors.
