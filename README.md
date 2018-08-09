# Instalación laravel db interface en laravel

##Configuración del Laravel

1- En el fichero composer.json añadir  
"minimum-stability": "dev", "prefer-stable": true 

en require: 

"omatech/editora-laravel-connector-dbinterface": "dev-master"

2 - hacer un composer update


3- Añadir providers en config/app.php
     Omatech\Editora\Connector\ConnectorServiceProvider::class,

4- ejecutar: php artisan vendor:publish
  
5- Configurar .env

6- Eliminar ruta por defecto en routes/web.php

7- En el directorio config del laravel existen los ficheros de configuración editora y editoradatabase

8- si modificamos el fichero editoradatabase, podemos regenerar la base de datos con el comando php artisan editora:create


##Commands Laravel

####- Generator
Genera de un archivo editoradatabase.php las tablas de la base de datos de la editora.

**_php artisan editora:create_**

####- Create MVC
Crear los archivos Model View Controller de las Clases de Editora (si no existen).

**_php artisan editora:createmvc_**


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







