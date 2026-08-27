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

    /*
    |--------------------------------------------------------------------------
    | ResellerInterface (do.de)
    |--------------------------------------------------------------------------
    |
    | Der Zugriff laeuft direkt: das Portal meldet sich selbst an, die
    | Zugangsdaten liegen verschluesselt unter „Schnittstellen". Die Sitzung
    | (coreSID) wird 15 Minuten im Cache gehalten — haeufige Anmeldungen
    | wertet der Anbieter als Angriff und sperrt das Konto.
    |
    */

    'resellerinterface' => [
        'endpoint' => env('RESELLERINTERFACE_ENDPOINT', 'https://core.resellerinterface.de'),
        'branch' => env('RESELLERINTERFACE_BRANCH', 'stable'),

        // Der Hauptaccount K58919. Ohne resellerId in den Zugangsdaten liest
        // der Anschluss ebenfalls den Hauptaccount. Weitere Konten kommen als
        // Liste dazu, etwa "59163" fuer den Subreseller.
        'reseller_id' => env('RESELLERINTERFACE_RESELLER_ID', '58919'),
        'reseller_ids' => env('RESELLERINTERFACE_RESELLER_IDS', ''),

        // Eine sicher freie Domain fuer den Verbindungstest. `domain/check`
        // liest nur und aendert nichts.
        'test_domain' => env('RESELLERINTERFACE_TEST_DOMAIN', 'wls-portal-verbindungstest-xyz.de'),
    ],

    'autodns' => [
        'endpoint' => env('AUTODNS_ENDPOINT', 'https://api.autodns.com/v1/'),

        // Unser Kontext ist 4. Er ist kein Geheimnis und steht deshalb hier
        // statt bei den Zugangsdaten; 1 waere das Testsystem von autoDNS.
        'context' => env('AUTODNS_CONTEXT', '4'),
    ],

];
