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
    | Der Zugriff laeuft ueber die Bruecke, die auf demselben Host liegt
    | (`domain-api-call`). Sie haelt die Zugangsdaten in ihrer eigenen `.env`
    | und uebernimmt Anmeldung und Sitzung; das Portal sieht davon nichts.
    |
    | Ein eigener Login beim Anbieter ist ausdruecklich untersagt: ein
    | selbstgebautes Login-Skript hat das Konto schon einmal gesperrt und damit
    | die DNS-Aenderungen aller Kunden blockiert.
    |
    */

    'resellerinterface' => [
        'command' => env('RESELLERINTERFACE_COMMAND', '/usr/local/bin/domain-api-call'),

        // Ohne resellerID liefert `domain/list` nur den Hauptaccount. Weitere
        // Konten kommen als Liste dazu, etwa "59163" fuer den Subreseller.
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
