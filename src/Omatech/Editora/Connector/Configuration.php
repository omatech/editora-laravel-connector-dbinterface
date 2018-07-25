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
    'extractNullValues'  => true,
		'useFrontendRoutes' => true // set this flag to false if you want to avoid automatic Editora Route interpretation
];

