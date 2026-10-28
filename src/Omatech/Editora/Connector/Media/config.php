<?php

return [
    // Laravel disk holding the public CMS media and the key prefix below its root.
    'disk' => 'editora_public',
    'prefix' => 'uploads',
    'cache_seconds' => 86400,

    // Extensions served inline, with the content type chosen here and never from stored metadata.
    'types' => [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'svg' => 'image/svg+xml', 'gif' => 'image/gif', 'avif' => 'image/avif',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf',
    ],

    // Any other extension: false answers 404, true serves it as an application/octet-stream attachment.
    'attachments' => false,

    'denied_prefixes' => ['private'],
    'denied_extensions' => [],
];
