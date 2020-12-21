<?php

$data = [
    'attributes_params' => true, //params in json value for new editora. Default false
	'truncate_users'=>false, //force delete user in database
	'users' => [
		// [name, type, default lang, rol_id, O|U] ("O" Omatech-super-admin or "U" normal user)
		['omatech', 'Omatech', 'ca', 1, 'O'],
		['test', 'Administrator', 'ca', 2, 'U']
    ],
    /** 
     * Default roles as 1 and 2
     * roles array add new roles from editora with classes restictions
     */
    'roles' => [
        //['id' => 3, 'name' => 'testrole', 'classes' => '10,20,30'],
    ],

    //languages for editora content
    'languages' => [
		10000 => 'ca',
		20000 => 'es',
		30000 => 'en',
    ],
    //menu groups
	'groups' => [
        1 => [
                'Principal',//internal name
                'caption'=>['Principal', 'Principal', 'Principal'],//Caption in CAT, ESP, ENG
                'classes' => [
                    1 => [//ID from class: Id 1 is reserved from Global class
                        'Global',
                        'caption'=>['Global'],//Caption in all languages
                        'attributes'=>['2,200'],//attributes from class
                        'relations' => [//relations with other classes
                            //relation_id => [internal name, childs=>id classes, caption=> caption in CAT, ESP, ENG]
                            1001  => ['main_menu', 'childs'=>'20,21,22', 'caption' =>['Menú principal ca', 'Menú principal', 'Main menu']],
                        ],
                        'editable'=>false //mark as editable class. Default true. If false users cannot create or delete, only edit
                    ],
                    10 => [//ID from class: Id 10 is reserved from Home class
                        'Home',
                        'caption'=>['Home'],
                        'attributes'=>['2,101,102,100,200,201,249,250,251,252,253,254,255,256,257,258,259,260,261,601,602,770'],
                        'relations' => [
                            10001 => ['home_blocks', 'childs'=>'30', 'caption' =>['Blocs destacats', 'Bloques destacados', 'Home blocks']],
                            10002 => ['shortcuts', 'childs'=>'20', 'caption' =>['Accessos directes', 'Accesos directos', 'Shortcuts']],
                            10003 => ['news_highlights', 'childs'=>'21', 'caption' =>['Notícies destacades', 'Notícias destacadas', 'News']],
                        ],
                        'editable'=>true, //mark as editable class. Default true
                        'seo_options' =>true,//add new tab in editora with seo attributes
                    ],
                ],
            ],
		2 => [
                'Secundaris',
                'caption'=>['Secundaris', 'Secundarios', 'Secondary'],
                'classes' => [
                    20 => [
                        'Pages',
                        'caption'=>['Pàgines', 'Páginas', 'Pages'],
                        'attributes'=>['2,200,201,600'],
                        'relations' => [
                            20001 => ['blocks', 'childs'=>'30', 'caption' =>['Bloques', 'Bloques', 'Blocks']],
                        ],
                        'seo_options' =>true,
                    ],
                    21 => [
                        'News',
                        'caption'=>['Noticies', 'Noticias', 'News'],
                        'attributes'=>['2,200'],
                        'relations' => [
                            21001 => ['blocks', 'childs'=>'30', 'caption' =>['Bloques', 'Bloques', 'Blocks']],
                            21002 => ['people', 'childs'=>'22', 'caption' =>['Bloques', 'Bloques', 'Blocks']],
                        ],
                        'seo_options' =>true,
                    ],
                    22 => [
                        'People',
                        'caption'=>['Persones', 'Personas', 'People'],
                        'attributes'=>['2,200'],
                        'relations' => [
                            22001 => ['blocks', 'childs'=>'30', 'caption' =>['Bloques', 'Bloques', 'Blocks']],
                        ],
                        'seo_options' =>true,
                    ]
                ],
            ],
		3 => [
                'Elements',
                'caption'=>['Elements', 'Elementos', 'Elements'],
                'classes' => [
                    30 => [
                        'Blocks',
                        'caption'=>['Blocs', 'Bloques', 'Blocks'],
                        'attributes'=>['201'],
                        'relations' => [
                            30001 => ['links', 'childs'=>'31', 'caption' =>['Bloques', 'Bloques', 'Blocks']],
                            30002 => ['documents', 'childs'=>'32', 'caption' =>['Bloques', 'Bloques', 'Blocks']],
                            30003 => ['people', 'childs'=>'22', 'caption' =>['Bloques', 'Bloques', 'Blocks']],
                        ],
                    ],
                    31 => [
                        'Links',
                        'caption'=>['Links'],
                        'attributes'=>['201'],
                        'relations' => [
                            31001 => ['internal_link', 'childs'=>'1,20,21,22', 'caption' =>['Bloques', 'Bloques', 'Blocks']],
                        ],
                    ],
                    32 => [
                        'Docs',
                        'caption'=>['Docs'],
                        'attributes'=>['201']
                    ]
                ]
            ],
	],
    'seo_attributes' => ['900,901,910,911,912,913'], //attributes for seo tab in editora



    //attributes_multi_lang_xxx insert attributes in all languages defined in array languages

	'attributes_order_string' => [//special string attribute with database index for extraction
		101 => ['surname', 'caption'=>['Cognoms', 'Apellidos', 'Surname']],
	],
	'attributes_order_date' => [//special date attribute with database index for extraction
		102 => ['order_date', 'caption'=>['Data noticia', 'Fecha noticia', 'News Date']],
	],
	'attributes_string' => [
		100 => ['nom', 'caption'=>['Nom ', 'Nombre', 'Name']],
	],
	'attributes_multi_lang_string' => [
		200 => ['title', 'caption'=>['Títol', 'Título', 'Title'], 'description'=>'una prueba de descripción'],
        201 => ['subtitle', 'caption'=>['Subtítol', 'Subtítulo', 'Subtitle']],
        
        //SEO Attributes
        910 => ['meta_title', 'caption' => ['Meta Title', 'Meta Title', 'Meta Title']],
        913 => ['alt_og_image', 'caption' => ['Alt Imatge facebook', 'Alt Imagen facebook', 'Alt facebook image']],
	],
	'attributes_textarea' => [
        249 => ['textarea', 'caption'=>['textarea ca', 'textarea es', 'textarea en']],
    ],
    'attributes_multi_lang_textarea' => [
        250 => ['lang_textarea', 'caption'=>['lang_textarea ca', 'lang_textarea es', 'lang_textarea en']],
        
        //SEO Attributes
        911 => ['meta_keywords', 'caption' => ['Meta Keywords', 'Meta Keywords', 'Meta Keywords']],
        912 => ['meta_description', 'caption' => ['Meta Description', 'Meta Description', 'Meta Description']],
	],
	'attributes_text' => [
		251 => ['text', 'caption'=>['text ca', 'text es', 'text en']],
    ],
    'attributes_multi_lang_text' => [
		280 => ['lang_text', 'caption'=>['lang_text ca', 'lang_text es', 'lang_text en']],
    ],
    
    'attributes_json' => [
    ],
    'attributes_multi_json' => [
        151 => ['json', 'caption'=>['Json', 'Json', 'Json'], 'params' => ['only_root'=>true]],//only_root params hide attribute for other users
    ],


	'attributes_date' => [
        252 => ['date', 'caption'=>['date ca', 'date es', 'date en']],
	],

	'attributes_color' => [//paint color selector and insert color 
        253 => ['color', 'caption'=>['color ca', 'color es', 'color en']],
    ],

	'attributes_num' => [
        254 => ['num', 'caption'=>['num ca', 'num es', 'num en']],
    ],
	'attributes_geolocation' => [//add google map searcher and save geolocation attributes
        255=> ['geolocation', 'caption'=>['geolocation ca', 'geolocation es', 'geolocation en']],
    ],
	'attributes_url' => [
        256=> ['url', 'caption'=>['url ca', 'url es', 'url en']],
    ],
    'attributes_multi_lang_url' => [
        257=> ['lang_url', 'caption'=>['lang_url ca', 'lang_url es', 'lang_url en']],
	],
	'attributes_file' => [
        258=> ['file', 'caption'=>['file ca', 'file es', 'file en']],
    ],
    'attributes_multi_lang_file' => [
        259=> ['lang_file', 'caption'=>['lang_file ca', 'lang_file es', 'lang_file en']],
    ],
	'attributes_video' => [//insert url from youtube or vimeo and save id from video and service
		260=> ['video', 'caption'=>['video ca', 'video es', 'video en']],
	],
    'attributes_multi_lang_video' => [
        261=> ['lang_video', 'caption'=>['lang_video ca', 'lang_video es', 'lang_video en']],
    ],
    'attributes_image' => [
        601 => ['profile_picture', 'caption' =>['Imatge perfil', 'Imágen perfil', 'Profile picture'], 'params'=>['size'=>['150x200']]],

        //SEO Attributes
        901 => ['og_image', 'caption' =>['Imatge facebook', 'Imagen facebook', 'facebook image'], 'params' => ['size' => ['1200x']]],
    ],
    'attributes_multi_lang_image' => [
        602 => ['lang_picture', 'caption' =>['Imatge ', 'Imágen ', 'Profile '], 'params'=>['size'=>['700x200']]],
    ],
    'attributes_grid_image' => [//preview image with grid with positions for width and heigth
        603 => ['grid_image', 'caption' =>['grid_image ca', 'grid_image es', 'grid_image en'], 'params'=>['size'=>['700x200']]],
    ],
	'attributes_lookup' => [//attribute lookup with options
        770 => ['image_position', 'caption' =>['Posició imatge', 'Posición imágen', 'Image position'],
                'params'=>['lookup'=>[//options for lookup, first option is default option
                    7001 => ['left', 'Esquerra', 'Izquierda', 'Left'],
                    7002 => ['right', 'Dreta', 'Derecha', 'Right']
                ]
            ]
        ],

        //SEO Attributes
        900 => ['meta_robots',
            'caption' =>['Meta Robots', 'Meta Robots', 'Meta Robots'],
            'params' => [
                'lookup' => [
                    9001 => ['index,follow', 'index,follow', 'index,follow', 'index,follow'],
                    9002 => ['noindex,nofollow', 'noindex,nofollow', 'noindex,nofollow', 'noindex,nofollow'],
                    9003 => ['index,nofollow', 'index,nofollow', 'index,nofollow', 'index,nofollow'],
                    9004 => ['noindex,follow', 'noindex,follow', 'noindex,follow', 'noindex,follow']
                ]
            ]
        ]
	],

];



