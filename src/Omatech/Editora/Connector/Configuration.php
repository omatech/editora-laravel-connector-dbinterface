<?php

return [
    'routeParams' => [
        [
            'language'
        ],
        [
            'language',
            'nice_url'
        ]
        /*,[
            'language',
            '::blog', Static Route
            'nice_url'
        ]*/
    ],
    'homeNiceUrl'        => false,
    /*
     * Zone in which editors type publishing dates. Every MySQL session uses it (as the offset of the moment), since Editora
     * compares those dates with NOW() and Laravel Cloud's MySQL runs in UTC. Empty: the server's zone, as before.
     */
    'databaseTimezone'   => env('EDITORA_DATABASE_TIMEZONE', 'Europe/Madrid'),
    'availableLanguages' => ['ca', 'es'],
    'defaultLanguage'    => 'ca',
    'ignoreBrowserLanguage' => false,
    'forcedLanguage'     => '',
    'adminAlias'         => 'admin',
    'middlewares'        => [],
    'useFrontendRoutes' => true, // set this flag to false if you want to avoid automatic Editora Route interpretation
    'controllersNamespace' => 'App\\Http\\Controllers\\Editora\\',
    'notFoundHttpException' => 'App\Exceptions\EditoraNotFoundHttpException',
];

