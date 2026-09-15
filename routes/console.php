<?php

use Illuminate\Support\Facades\Schedule;

// Geplante Preisaenderungen greifen zum Wirksamkeitsdatum.
Schedule::command('preise:faellige-anwenden')
    ->dailyAt('00:05')
    ->timezone(config('app.timezone'))
    ->onOneServer();

/*
 * Taeglicher Abgleich des Domainbestands (§60).
 *
 * Nachts, weil er nichts erledigt, worauf jemand wartet, und weil die
 * Schnittstellen dann ruhiger sind. Ein Fehlschlag wird nicht wiederholt —
 * bei ResellerInterface verlaengert jeder weitere Versuch eine Sperre, und
 * der naechste planmaessige Lauf ist frueh genug. Das Protokoll haelt ihn
 * fest; sichtbar wird er unter „Schnittstellen".
 */
Schedule::command('registrar:sync --geplant')
    ->dailyAt('03:20')
    ->timezone(config('app.timezone'))
    ->onOneServer()
    ->withoutOverlapping();
