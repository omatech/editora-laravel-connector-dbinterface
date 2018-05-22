<?php
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

use App\Models\Eloquent\StaticTexts;

if (!function_exists('_t')) {
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
}