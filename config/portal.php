<?php

return [

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

];
