<?php
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

use Omatech\Editora\Connector\Models\StaticTexts;

if (!function_exists('_statictext')) {
    /**
     * Shorthand function for translating text.
     *
     * @param string $text
     * @param array  $replacements
     * @param string $toLocale
     *
     * @return string
     */
    function _statictext($text, $replacements = [], $locale = null) {
        $message = new StaticTexts();
        return $message->getOneStaticText($text, $replacements, $locale);
    }

    function _allStatictext($locale = null) {
        $message = new StaticTexts();
        if($locale!=null){
            return $message->todos_lang($locale);
        }else{
            return $message->todos();
        }
    }
}