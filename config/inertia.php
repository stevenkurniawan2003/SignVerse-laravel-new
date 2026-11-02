<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Bawaan paket mencari halaman di `resources/js/pages`, sedangkan proyek ini
    | memakai `resources/js/Pages`. Penulisannya harus sama persis karena sistem
    | berkas Linux membedakan huruf besar dan kecil.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => [
            'vue',
        ],

    ],

];
