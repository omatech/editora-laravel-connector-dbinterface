<?php

namespace Omatech\Editora\Connector;

use App;
use Omatech\Editora\Extractor\Extractor;
use Omatech\Editora\Loader\Loader;

class EditoraModel {

	public $debugMessages = "";

/*	public static function getDBArray() {
		return $editora_conn_array = array(
			'dbname' => env('DB_DATABASE'),
			'user' => env('DB_USERNAME'),
			'password' => env('DB_PASSWORD'),
			'host' => env('DB_HOST'),
			'driver' => 'pdo_mysql',
			'charset' => 'utf8'
		);
	}
*/
	public static function defaultParams($params=array())
	{
		if (!array_key_exists('lang', $params)) {
			$params['lang'] = App::getLocale();
		}

		if (!array_key_exists('metadata', $params)) {
			$params['metadata'] = true;
		} 

		if (!array_key_exists('timings', $params)) {
			$params['timings'] = true;
		}
		if (!array_key_exists('show_inmediate_debug', $params)) {
			$params['show_inmediate_debug'] = false;
		}

        if (!array_key_exists('debug', $params)) {
            $params['debug'] = env('APP_DEBUG');
        }

		return $params;
	}
	
	
	public static function extractor($params = array()) {

		//$editora_conn_array = self::getDBArray();
		$params=self::defaultParams($params);
		$extractor=App::make('Extractor');
		$extractor->setParams($params);
		//$extractor = new Extractor($editora_conn_array, $params);
		return $extractor;
	}
	
	public static function loader($params = array()) {
		//$editora_conn_array = self::getDBArray();
		$params=self::defaultParams($params);
		$loader=App::make('Loader');
		$loader->setParams($params);
		//$loader = new Loader($editora_conn_array, $params);
		return $loader;
	}	

}
