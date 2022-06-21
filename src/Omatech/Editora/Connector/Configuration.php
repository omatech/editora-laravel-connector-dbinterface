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

