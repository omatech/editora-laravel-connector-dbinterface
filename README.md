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

--help this help!
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








