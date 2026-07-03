<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enterprise Logging System Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the enterprise logging settings, such as whether
    | logs should be dispatched to the background queue or processed
    | synchronously during requests.
    |
    */

    'enterprise' => [
        'queue' => env('ENTERPRISE_LOGGING_QUEUE', false),
    ],

];
