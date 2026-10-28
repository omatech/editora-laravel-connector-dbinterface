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

`Cache\CachedExtractor` caches the complete graph returned by the listed extractor methods. An entry is fresh for `CMS_CACHE_TTL` seconds (60) and, up to `CMS_CACHE_STALE_TTL` (300), is served at once and refreshed after the response. Nested calls, preview, debug and `avoid_cache` always read the database. Register it in a service provider's `boot()`:

```php
\Omatech\Editora\Connector\Cache\CachedExtractor::register($this->app, ['findInstanceByIdAllElements', 'findChildrenInstances']);
```

Leave out methods whose arguments come from free text, such as a search building `findInstancesInList` ids: every distinct query would become a cache entry. `Cache\CachedStaticTexts::register($this->app)` does the same for static texts, once per language.

`CMS_CACHE_STORE` selects the store (default: the application's), `CMS_CACHE_ENABLED=false` turns it off and changing `CMS_CACHE_VERSION` stops serving every older copy. To invalidate on publication instead, bind `Cache\CacheVersion` to your own implementation.

## Page cache

`Cache\PageCache` is a global middleware that serves `/{language}` and `/{language}/{slug}` to every visitor from one copy per cache version, for 300 seconds. It only stores a page that another visitor may receive: a complete `200 text/html` document of at most 512 KiB, without the session's CSRF token, `_token` fields, a `csrf-token` meta, a `nonce` or a VarDumper dump, rendered for a session holding only language and navigation, and adding no header or cookie besides the session ones. Answers carry `X-Page-Cache: HIT|MISS|BYPASS` and `Cache-Control: no-store`, so an edge cache never keeps a page the version no longer reaches. At most 32 MiB of new pages are stored per 300-second window. Register it after any middleware whose headers every response needs:

```php
protected $middleware = [
    // ...
    \App\Http\Middleware\SecurityHeaders::class,
    \Omatech\Editora\Connector\Cache\PageCache::class,
];
```

`PAGE_CACHE_STORE` selects the store and `PAGE_CACHE_ENABLED=false` turns it off.

## Media from a private bucket

`Media\PublicMediaController` serves CMS media from a private S3 bucket under the site's own URL, so the bucket host never reaches the browser:

```php
Route::get('uploads/{path}', \Omatech\Editora\Connector\Media\PublicMediaController::class)->where('path', '.*');
```

It reads `editora-media.prefix` + path from the `editora-media.disk` filesystem disk, which must be a complete `s3` disk with an HTTPS endpoint; otherwise it answers 404 and the web server serves local media. It needs `aws/aws-sdk-php`, not the Flysystem S3 adapter. Responses are streamed with `Cache-Control: public, max-age=86400` (`cache_seconds`), `ETag`, `Last-Modified`, 304 answers and a single forwarded `bytes=` range. The content type comes from `editora-media.types`, never from stored metadata; every answer carries `nosniff` and SVG adds a sandbox CSP. Other extensions answer 404, or download as attachments with `attachments => true`. Hidden paths, traversal, backslashes, `denied_prefixes` and `denied_extensions` answer 404 without reaching storage; a missing object answers 404 and a storage failure 502 without caching, and is reported.
