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

];
