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

    function _getTextCutted($titol, $caracters=200, $tags=''){
        $titol = strip_tags($titol, $tags);
        $res = "";
        $titolx = rtrim($titol," \t.");

        if (strlen($titolx) <= $caracters) {
            $res = $titolx;
        }
        else {
            $str = $titol;
            $limit = $caracters;
            $very_small_factor = 0.7;
            $small_factor = 0.4;
            $large_factor = 0.4;
            $very_large_factor = 0.6;

            $str = substr($str,0,$limit+10);
            $chars = '*,\',f,i,í,ï,j,l,t,(,)';
            $chars_arr=explode(',', $chars);
            $count=0;

            foreach ($chars_arr as $char) { // for each char, lets see the number of occurrences in string
                if($char=="*") $char=",";
                $count+=strlen($str)-strlen(str_replace($char, '', $str));
            }
            $very_small = $count;


            $str = substr($str,0,$limit+10);
            $chars =  'r,s,z,J,I,Í,Ï, ';
            $chars_arr=explode(',', $chars);
            $count=0;

            foreach ($chars_arr as $char) { // for each char, lets see the number of occurrences in string
                if($char=="*") $char=",";
                $count+=strlen($str)-strlen(str_replace($char, '', $str));
            }
            $small = $count;


            $str = substr($str,0,$limit+10);
            $chars =  'a,b,d,e,g,o,p,q,F,L,P,R,S,T,Y,Z';
            $chars_arr=explode(',', $chars);
            $count=0;

            foreach ($chars_arr as $char) { // for each char, lets see the number of occurrences in string
                if($char=="*") $char=",";
                $count+=strlen($str)-strlen(str_replace($char, '', $str));
            }
            $large = $count;

            $str = substr($str,0,$limit+10);
            $chars =  'm,w,A,À,Á,B,C,D,E,G,H,K,M,N,Ñ,O,Ò,Ó,Q,U,Ú,Ü,V,W,X';
            $chars_arr=explode(',', $chars);
            $count=0;

            foreach ($chars_arr as $char) { // for each char, lets see the number of occurrences in string
                if($char=="*") $char=",";
                $count+=strlen($str)-strlen(str_replace($char, '', $str));
            }
            $very_large = $count;


            $limit = $limit+($very_small*$very_small_factor)+($small*$small_factor);
            $limit = $limit-($large*$large_factor)-($very_large*$very_large_factor);
            $caracters = round($limit);


            if (strlen($titolx)<=$caracters) { // retorno directament l'string, es prou curt
                $res=$titolx;
            }
            else { // es massa llarg, recorrem l'array i parem quan no pugem mes
                $arr=explode(" ",$titolx);
                $cont=0;
                foreach($arr as $paraula) {
                    if ((strlen($res)+strlen($paraula)+1)<=$caracters) {
                        if ($cont!=0) $res.=' ';
                        $res.=$paraula;
                    }
                    else {
                        $res.='...';
                        break;
                    }
                    $cont++;
                }
            }
        }

        return $res;
    }


}