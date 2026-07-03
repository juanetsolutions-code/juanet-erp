<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is used for general caching requirements
    | such as session state or compiled templates.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the cache "stores" for your application
    | as well as their drivers. You may even define multiple stores of the
    | same driver to group different kinds of items inside your cache.
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'table' => env('CACHE_DATABASE_TABLE', 'cache'),
            'connection' => env('CACHE_DATABASE_CONNECTION'),
            'lock_connection' => env('CACHE_DATABASE_LOCK_CONNECTION'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('CACHE_REDIS_CONNECTION', 'cache'),
            'lock_connection' => env('CACHE_REDIS_LOCK_CONNECTION', 'default'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing a shared cache server (like Redis), we might find
    | other applications using the same server. So, we must prefix all
    | of the cache keys to avoid conflicts with other applications.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'juanet'), '_').'_cache_'),

];
