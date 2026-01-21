<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'json' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel-json.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'formatter' => \Monolog\Formatter\JsonFormatter::class,
        ],
    ],
];

