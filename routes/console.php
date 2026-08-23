<?php

use Illuminate\Support\Facades\Schedule;

// Geplante Preisaenderungen greifen zum Wirksamkeitsdatum.
Schedule::command('preise:faellige-anwenden')
    ->dailyAt('00:05')
    ->timezone(config('app.timezone'))
    ->onOneServer();
