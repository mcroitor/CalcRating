<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | Adding a locale only requires listing it here, adding lang/<locale>/ and
    | lang/<locale>.json files. No application code changes are needed.
    |
    */

    'supported' => ['en', 'ru'],

    'default' => 'en',

    'fallback' => 'en',

    'cookie' => 'locale',

    // One year, in minutes.
    'cookie_lifetime' => 525600,

    'names' => [
        'en' => 'English',
        'ru' => 'Русский',
    ],

    // Human-readable date formats per locale. Machine formats (ISO, XLSX
    // values) are never localized.
    'date_format' => [
        'en' => 'M j, Y',
        'ru' => 'd.m.Y',
    ],

    'datetime_format' => [
        'en' => 'M j, Y H:i',
        'ru' => 'd.m.Y H:i',
    ],
];
