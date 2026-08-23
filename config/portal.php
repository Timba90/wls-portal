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

    ],

];
