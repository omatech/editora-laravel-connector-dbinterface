<?php
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;


if (!function_exists('_t')) {

    function cookie_not_exist($cookie_name=''){
        if(!\Omatech\Editora\Utils\Strings::checkCookieExist($cookie_name)){
            return true;
        }else{
            return false;
        }
    }

    function _geolocation($location) {
        $loc = explode('@', $location);
        $loc = explode(':', $loc[0]);
        $geolocation = array('lat'=>$loc[0], 'lng'=>$loc[1]);

        return $geolocation;
    }

    function _mapGeolocation($location) {
        $loc = explode('@', $location);
        $loc = explode(':', $loc[0]);
        $geolocation = $loc[0].','.$loc[1];

        return $geolocation;
    }

    function _link($link){
        if (stristr($link, 'http')){
            return $link;
        }else{
            return 'http://'.$link;
        }
    }

    function _getdate($date){
        $lang = App::getLocale();
        switch ($lang){
            case 'ca':
                $locale = 'ca_ES';
                break;
            case 'es':
                $locale = 'es_ES';
                break;
            default:
                $locale = 'en_GB';
                break;
        }

        setlocale(LC_TIME, $locale.'.UTF-8');
        return (string) (new Carbon($date))->formatLocalized('%d/%m/%Y');
    }


    function _parsetext($text, $class='', $list_class=''){
        $text = str_replace('<ul>', '</p><ul class="'.$list_class.'">', $text);
        $text = str_replace('</ul>', '</ul><p class="'.$class.'">', $text);

        $text = str_replace('<ol>', '</p><ol  class="'.$list_class.'">', $text);
        $text = str_replace('</ol>', '</ol><p class="'.$class.'">', $text);

        return $text;
    }

    function _alttext($text){
        return strip_tags(str_replace('"', "'", $text));
    }

    function _videolink($video){
        $player = explode(':', $video);
        switch ($player[0]){
            case 'youtube':
                $link = 'https://www.youtube.com/watch?v='.$player[1];
                break;
            case 'vimeo':
                $link = 'https://vimeo.com/'.$player[1];
                break;
            default:
                $link = $video;
                break;
        }
        return $link;
    }

    function _videoembed($video){

        $arg_vid = explode(':', $video);
        $id_video = $arg_vid[1];

        switch ($arg_vid[0]){
            case 'youtube':
                $embed = "https://www.youtube.com/embed/".$id_video;
                break;
            case 'vimeo':
                $embed = "https://player.vimeo.com/video/".$id_video."?color=#E9AF2A&portrait=0";
                break;
            default:
                $embed = $video;
                break;
        }
        return $embed;
    }

    function _get_nice_from_language($instance, $lang){
        if(isset($instance['id']) && $instance['id']!=0){//si es editora buscamos la url por idioma
            $url = \Omatech\Editora\Utils\Editora::get_nice_from_id($instance['id'], $lang);
        }else{//si no es editora simplemente le cambiamos el idioma, tiene que estar configurado en el routes
            $link = explode('/', $instance['link']);
            $url = $link[2];
        }
        return $url;
    }

}