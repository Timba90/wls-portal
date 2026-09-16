<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marke
    |--------------------------------------------------------------------------
    |
    | Wortmarke und Zusatz aus dem Entwurf. Getrennt von APP_NAME, damit der
    | Anwendungsname (Seitentitel, E-Mails) unabhängig bleibt.
    |
    */

    'brand' => [
        'name' => env('BRAND_NAME', 'weblab studio'),
        'tagline' => env('BRAND_TAGLINE', 'Interne Verwaltung'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Passwortregeln
    |--------------------------------------------------------------------------
    |
    | Anzeige der Passwortanforderungen in TallStackUI-Passwortfeldern. Die
    | eigentliche Durchsetzung erfolgt serverseitig über Password::defaults()
    | im AppServiceProvider — diese Liste hält die Oberfläche im Gleichklang.
    |
    */

    'password_rules' => ['min:12', 'mixed', 'numbers', 'symbols'],

    /*
    |--------------------------------------------------------------------------
    | Dokumente
    |--------------------------------------------------------------------------
    |
    | Dokumente liegen in privatem, S3-kompatiblem Object Storage. Es gibt keine
    | öffentlichen URLs — der Zugriff läuft ausschließlich über die Anwendung.
    |
    | Grundsätzlich sind alle Dateitypen erlaubt. Gefährliche ausführbare
    | Endungen werden über die Blockliste gesperrt. Eine Malware-Prüfung findet
    | bewusst nicht statt.
    |
    */

    'documents' => [

        'disk' => env('DOCUMENTS_DISK', 's3'),

        'max_size_mb' => (int) env('DOCUMENTS_MAX_SIZE_MB', 100),

        'blocked_extensions' => [
            'exe', 'msi', 'bat', 'cmd', 'com', 'cpl', 'scr', 'pif', 'vb', 'vbs',
            'vbe', 'js', 'jse', 'wsf', 'wsh', 'ps1', 'psm1', 'reg', 'lnk',
            'hta', 'jar', 'app', 'dmg', 'deb', 'rpm', 'sh', 'bash', 'so', 'dll',
            'phar', 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | MCP-Zugang
    |--------------------------------------------------------------------------
    |
    | Der MCP-Server stellt den Datenbestand als Werkzeuge für KI-Clients
    | bereit — lesend und schreibend, einschließlich endgültigem Löschen und
    | dem direkten Überschreiben von Preisen ohne Preisverlauf.
    |
    | Der Zugang läuft über persönliche Tokens. Ein Token trägt die vollen
    | Rechte seines Benutzers; es gibt keine feinere Abstufung. Tokens werden
    | mit `php artisan portal:mcp-token` ausgestellt und widerrufen.
    |
    | `enabled` schaltet den Endpunkt insgesamt ab, ohne dass Tokens
    | zurückgezogen werden müssen.
    |
    */

    'mcp' => [

        'enabled' => (bool) env('MCP_ENABLED', true),

        'path' => env('MCP_PATH', 'mcp/portal'),

        'rate_limit' => env('MCP_RATE_LIMIT', '60,1'),

        'token_expiration_days' => (int) env('MCP_TOKEN_EXPIRATION_DAYS', 90),

        /*
        |----------------------------------------------------------------------
        | OAuth
        |----------------------------------------------------------------------
        |
        | Der zweite Weg in den Server, neben den persoenlichen Tokens: ein
        | Client verbindet sich selbst und handelt danach im Namen des
        | Benutzers, der zugestimmt hat.
        |
        | Clients werden von Hand angelegt (`php artisan passport:client`).
        | Eine offene Selbstregistrierung gibt es bewusst nicht — alle Seiten
        | ausser Anmeldung und Passwort-Ruecksetzung sind authentifizierungs-
        | pflichtig, und ein offener Endpunkt waere die Ausnahme davon.
        |
        */

        'oauth' => [

            // Wie lange ein Zugriffstoken gilt. Kurz gehalten: der Client holt
            // sich mit dem Refresh-Token ein neues.
            'access_token_minutes' => (int) env('MCP_OAUTH_ACCESS_TOKEN_MINUTES', 60),

            /*
            |------------------------------------------------------------------
            | Client-ID-Metadatendokumente
            |------------------------------------------------------------------
            |
            | Der dritte Weg, einen Client bekannt zu machen — neben „von Hand
            | anlegen“ und der offenen Selbstregistrierung, die es hier nicht
            | gibt. Der Client schickt als Kennung eine HTTPS-Adresse, unter
            | der er beschreibt, wer er ist und wohin zurückgeleitet werden
            | soll. Er weist sich also dadurch aus, dass er eine Adresse
            | kontrolliert. ChatGPT verbindet sich so.
            |
            | `allowed_hosts` begrenzt, wessen Dokumente wir überhaupt holen.
            | Leer hieße: jeder im Netz darf bei uns einen Zustimmungsdialog
            | auslösen. Zustimmen müsste weiterhin ein angemeldeter Benutzer —
            | aber ein Dialog, den niemand zu sehen bekommt, kann auch
            | niemanden täuschen.
            |
            */

            'client_documents' => [

                'enabled' => (bool) env('MCP_OAUTH_CLIENT_DOCUMENTS', true),

                'allowed_hosts' => array_values(array_filter(array_map(
                    trim(...),
                    explode(',', (string) env('MCP_OAUTH_CLIENT_DOCUMENT_HOSTS', 'chatgpt.com,openai.com'))
                ))),

                // Wie lange ein geholtes Dokument gilt, bevor wir erneut
                // nachsehen.
                'cache_minutes' => (int) env('MCP_OAUTH_CLIENT_DOCUMENT_CACHE_MINUTES', 1440),

                // Wie lange ein zuletzt geprüftes Dokument eine Störung beim
                // Client überbrückt.
                'grace_days' => (int) env('MCP_OAUTH_CLIENT_DOCUMENT_GRACE_DAYS', 7),

                // Aus der Spezifikation: mehr als 5 KB lesen wir nicht.
                'max_bytes' => 5120,

                'timeout' => 5,
            ],

            // Wie lange ein Refresh-Token gilt — danach ist eine erneute
            // Zustimmung noetig.
            'refresh_token_days' => (int) env('MCP_OAUTH_REFRESH_TOKEN_DAYS', 90),

        ],

    ],

];
