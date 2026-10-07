<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Medias
    |--------------------------------------------------------------------------
    |
    | Downloads made when a media is created from a URL, with the medias
    | repository's createFromPath().
    |
    */

    'medias' => [
        'download' => [
            // Hosts that medias can be downloaded from, for example
            // ['cdn.example.com', '*.example.com'], where `*` matches any
            // characters. Redirects must stay on these hosts too. null allows
            // every host.
            'allowed_hosts' => null,

            // Maximum size of a downloaded file, in bytes. null removes the
            // limit.
            'max_size' => 1024 * 1024 * 1024,
        ],
    ],

];
