# Installation of a new frontend project using editora-laravel-connector-dbinterface

## Laravel Setup

### Create the new laravel empty project

composer create-project --prefer-dist laravel/laravel editora-test "5.5.*"

### Setup you .env file

### In the composer.json file add the stability settings in the root of the file and change the name and the description of the project, for example:

    "name": "editora-test",
    "description": "Editora Frontend",
		"minimum-stability": "dev",
		"prefer-stable": true,

### Add in require section the editora-laravel-connector-dbinterface: 

"omatech/editora-laravel-connector-dbinterface": "dev-master"

### Do a composer update


### Add a new Provider in config/app.php file
     Omatech\Editora\Connector\ConnectorServiceProvider::class,

### Run: php artisan vendor:publish

### Remove default route in routes/web.php

### In config folder there're the two files needed for editora setup editora.php sets the language and different editora options editoradatabase.php sets the editora structure 

### Modify editoradatabase.php and apply changes running

php artisan editora:create

### Generate fake content for editora

**_php artisan editora:fakecontent --delete_previous_data_**

##Commands Laravel

####- Generator
Genera de un archivo editoradatabase.php las tablas de la base de datos de la editora.

**_php artisan editora:create_**

####- Create MVC
Crear los archivos Model View Controller de las Clases de Editora (si no existen).

**_php artisan editora:createmvc_**

**Argumentos:**

{--include_classes=}

--include_classes generate only this class_ids, comma separated

**Mejoras:**
Falta crear argumento de force para borrar si o si las clases.

####- Fake Content
Crea contenido aleatorio para todas las clases. 

**_php artisan editora:fakecontent_**

**Ejemplo con argumento:**

php artisan editora:fakecontent --exclude_classes=1,10,11,12,13

**Argumentos:**

{--num_instances=} {--include_classes=} {--exclude_classes=} {--pictures_theme=} {—debug=}

--help this help!
--num_instances number of instance to create for each class
--include_classes generate only this class_ids, comma separated
--exclude_classes generate all but this class_ids, comma separated
--pictures_theme generate pictures themed with that word, default:cats you can use abstract, animals, business, cats, city, food, nightlife, fashion, people, nature, sports, technics, transport
--debug show all sqls (if not present false)
--delete_previous_data **USE WITH CAUTION**, if set deletes all the previous data before generating the fake data


**Mejoras:**
Falta añadir contenido aleatorio para algunos atributos (mapas, date,...). 


####- Modernize
Añade la nueva estructura de la editora en la base de datos. (indices, columnas nuevas, batchs_ids, etc)

**_php artisan editora:modernize_**







