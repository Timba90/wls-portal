<?php

namespace App\Jobs;

use App\Actions\Lexoffice\SyncLexofficeRecurringTemplates;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Der Abgleich aus der Oberflaeche heraus.
 *
 * Ein erster Lauf liest jede Vorlage einzeln und dauert bei zwei Anfragen pro
 * Sekunde gut eine Minute — zu lang fuer eine Livewire-Anfrage.
 */
class SyncLexofficeRecurringTemplatesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function handle(SyncLexofficeRecurringTemplates $sync): void
    {
        $sync->handle();
    }
}
