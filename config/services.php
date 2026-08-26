<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registrare
    |--------------------------------------------------------------------------
    |
    | Zugangsdaten der Domain-Schnittstellen. Sie stehen ausschliesslich in der
    | Umgebung, nie im Repository. Ohne Eintrag meldet der Import, dass der
    | Anschluss nicht eingerichtet ist, statt zu versuchen es ohne zu tun.
    |
    */

    'inwx' => [
        'endpoint' => env('INWX_ENDPOINT', 'https://api.domrobot.com/jsonrpc/'),
        'username' => env('INWX_USERNAME'),
        'password' => env('INWX_PASSWORD'),
        // Nur noetig, wenn das Konto eine Zwei-Faktor-Anmeldung verlangt.
        'shared_secret' => env('INWX_SHARED_SECRET'),
    ],

    'domain_reselling' => [
        'endpoint' => env('DOMAIN_RESELLING_ENDPOINT', 'https://api.domainreselling.de/api/call.cgi'),
        'username' => env('DOMAIN_RESELLING_USERNAME'),
        'password' => env('DOMAIN_RESELLING_PASSWORD'),
    ],

];
