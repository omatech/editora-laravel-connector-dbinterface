<?php

$data = array(
    'truncate_users'=>true,
    'users' => array(
        // name, type, default lang, rol_id, O|U (Omatech-super-admin or normal user)
        array('omatech', 'Omatech', 'ca', 1, 'O'),
        array('admin', 'Administrador', 'ca', 2, 'U')
    ),
    'languages' => array(
        10000 => 'ca',
        20000 => 'es',
        30000 => 'en'
    ),
    'groups' => array(
        'Principal' => 1,
        'Secundaris' => 2,
        'Blocs' => 3
    ),
    'classes' => array(
        'Principal' => [
            1 => ['Global', 'Global'],
            10 => ['Home', 'Home'],
        ],
        'Secundaris' => [
            20 => ['Page', 'Pàgina'],

        ],
        'Blocs' => [
        ]
    ),
    'attributes_string' => array(
        //id=>array(tag, caption_ca, caption_es, caption_en OR id=>tag
        100 => array('nom', 'Nom', 'Nombre', 'Name'),
    ),
    'attributes_multi_lang_string' => array(
        //id=>array(tag, caption_ca, caption_es, caption_en OR id=>tag
        200 => ['title', 'Títol', 'Título', 'Title'],
    ),
    'attributes_multi_lang_textarea' => array(
        //id=>array(tag, caption_ca, caption_es, caption_en OR id=>tag
        400 => ['text', 'Text', 'Texto', 'Text'],
    ),
    'attributes_textarea' => array(
        //id=>array(tag, caption_ca, caption_es, caption_en OR id=>tag
    ),
    'attributes_text' => array(
        //id=>array(tag, caption_ca, caption_es, caption_en OR id=>tag
    ),
    'attributes_multi_lang_image' => array(
        //id=>array(tag, caption_ca, caption_es, caption_en OR id=>tag
    ),
    'attributes_image' => array(
        //id=>array(tag, caption_ca, caption_es, caption_en OR id=>tag
//        600 => ['img_page', 'Imatge pàgina', 'Imágen Página', 'Page Image']
    ),
    'images_sizes' => array(
//        600 => '780x200'

    ),
    'attributes_multi_lang_file' => array(
    ),
    'attributes_date' => array(
    ),
    'attributes_num' => array(),
    'attributes_geolocation' => array(),
    'attributes_url' => array(
    ),
    'attributes_multi_lang_url' => array(
    ),
    'attributes_file' => array(),
    'attributes_video' => array(
    ),
    'attributes_lookup' => array(
    ),
    'lookups' => array(
        /* '70,icon' => [
            7001 => ['little', 'Pequeña', 'Little', 'Petita'],
            7002 => ['big', 'Grande', 'Big', 'Gran']
        ]*/
    ),
    'relations' => array(
//        1001 =>'1,23,24,25,27,33,42',

    ),
    'relation_names' => array(
//        1001=>['Menú principal', 'main_menu'],

    ),
    'attributes_classes' => array(
        1  =>'2,200',
        10 =>'2,200',

    ),
    'roles' => array(
        //array('id' => 3, 'name' => 'testrole', 'classes' => '10,20,30'),
    )
);

