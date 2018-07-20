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
