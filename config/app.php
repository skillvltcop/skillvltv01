<?php

return [

    'name' => env('APP_NAME', 'Laravel'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => 'UTC',

    'locale' => env('APP_LOCALE', 'ar'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'ar'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'auth' => [
        'defaults' => [
            'guard' => env('AUTH_GUARD', 'web'),
            'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
        ],
        'guards' => [
            'web' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
        ],
        'providers' => [
            'users' => [
                'driver' => 'eloquent',
                'model' => App\Models\User::class,
            ],
        ],
    ],

    'logging' => [
        'channels' => [
            'stack' => [
                'driver' => 'stack',
                'channels' => explode(',', (string) env('LOG_STACK', 'single')),
                'ignore_exceptions' => false,
            ],
        ],
    ],

    'session' => [
        'driver' => env('SESSION_DRIVER', 'database'),
        'lifetime' => (int) env('SESSION_LIFETIME', 120),
        'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
        'encrypt' => env('SESSION_ENCRYPT', false),
        'files' => storage_path('framework/sessions'),
        'connection' => env('SESSION_CONNECTION'),
        'table' => env('SESSION_TABLE', 'sessions'),
        'store' => env('SESSION_STORE'),
        'lottery' => [2, 100],
        'cookie' => env(
            'SESSION_COOKIE',
            'laravel-session',
        ),
        'path' => '/',
        'domain' => env('SESSION_DOMAIN'),
        'secure' => env('SESSION_SECURE_COOKIE'),
        'http_only' => true,
        'same_site' => 'lax',
    ],

    'broadcasting' => [
        'default' => env('BROADCAST_CONNECTION', 'null'),
    ],

    'cache' => [
        'stores' => [
            'array' => ['driver' => 'array'],
        ],
        'prefix' => env('CACHE_PREFIX', 'laravel-cache'),
    ],

    'filesystems' => [
        'default' => env('FILESYSTEM_DISK', 'local'),
        'disks' => [
            'local' => [
                'driver' => 'local',
                'root' => storage_path('app/private'),
                'serve' => false,
                'throw' => false,
            ],
        ],
    ],

    'queue' => [
        'default' => env('QUEUE_CONNECTION', 'database'),
    ],

    'view' => [
        'paths' => [
            resource_path('views'),
        ],
        'compiled' => env(
            'VIEW_COMPILED_PATH',
            realpath(storage_path('framework/views')),
        ),
    ],

    'services' => [],
];
