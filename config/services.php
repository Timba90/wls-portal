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
    | Hier steht nur, wohin die Anschluesse sprechen. Die Zugangsdaten liegen
    | verschluesselt in der Tabelle `integration_credentials` (§50) und werden
    | in der Oberflaeche gepflegt — nicht in der Umgebung und schon gar nicht
    | im Repository.
    |
    */

    'inwx' => [
        'endpoint' => env('INWX_ENDPOINT', 'https://api.domrobot.com/jsonrpc/'),
    ],

    'domain_reselling' => [
        'endpoint' => env('DOMAIN_RESELLING_ENDPOINT', 'https://api.domainreselling.de/api/call.cgi'),
    ],

];
