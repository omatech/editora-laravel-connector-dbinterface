<?php

namespace Omatech\Editora\Connector\Models;

use Illuminate\Database\Eloquent\Model;
use App;

class StaticTexts extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'omp_static_text';

    public static function todos() {
        $txt = "";
        $texts = self::where('language', App::getLocale())->get();

        foreach($texts as $text) {
            $txt[$text->text_key] = $text->text_value;
        }

        return $txt;
    }

    public static function todos_lang($lang) {
        $txt = "";
        $texts = self::where('language', $lang)->get();

        foreach($texts as $text) {
            $txt[$text->text_key] = $text->text_value;
        }

        return $txt;
    }

    public function getOneStaticText($text = '', $replacements = [], $locale = null){
        try {
            ($locale)?null:$locale = App::getLocale();

            $this->validateText($text);

            $staticText = $this->firstOrCreateText($locale, $text);

            foreach ($replacements as $key=>$value){
                $staticText = str_replace('#'.$key.'#', $value, $staticText);
            }

            return $staticText;

        } catch (\Illuminate\Database\QueryException $e) {
            dump($e);
            return "error_database";
        }

    }

    protected function validateText($text) {
        if (!is_string($text)) {
            $message = 'Invalid Argument. You must supply a string to be translated.';
            throw new InvalidArgumentException($message);
        }
        return true;
    }

    protected function firstOrCreateText($locale, $text) {

        $staticText =  self::where(['language'=>$locale, 'text_key'=>$text])->first();
        if($staticText){
            $staticText = $staticText['text_value'];
        } else {
            self::insert(['language'=>$locale, 'text_key'=>$text, 'text_value'=>'##'.$text.'##']);
            $staticText = '##'.$text.'##';

        }

        return $staticText;
    }

}