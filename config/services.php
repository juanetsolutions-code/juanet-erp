<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, Stripe, and more. This file provides a sane
    | default location for this type of information.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safaricom Daraja M-PESA API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for Safaricom Daraja Lipa Na M-PESA payment integration.
    |
    */
    'mpesa' => [
        'env' => env('MPESA_ENV', 'sandbox'), // sandbox or production
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'passkey' => env('MPESA_PASSKEY'),
        'callback_url' => env('MPESA_CALLBACK_URL', 'https://juanet.enterprise/api/payments/m-pesa-callback'),
        'initiator_username' => env('MPESA_INITIATOR_USERNAME'),
        'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),
    ],

];
