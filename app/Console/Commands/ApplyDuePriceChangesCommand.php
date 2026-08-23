<?php

namespace App\Console\Commands;

use App\Actions\Pricing\ApplyDuePriceChanges;
use Illuminate\Console\Command;

/**
 * Setzt geplante Preisaenderungen zum Wirksamkeitsdatum um.
 *
 * Laeuft taeglich ueber den Scheduler.
 */
class ApplyDuePriceChangesCommand extends Command
{
    protected $signature = 'preise:faellige-anwenden';

    protected $description = 'Setzt alle fälligen geplanten Preisänderungen wirksam.';

    public function handle(ApplyDuePriceChanges $applyDuePriceChanges): int
    {
        $anzahl = $applyDuePriceChanges();

        $this->info($anzahl === 1
            ? '1 Preisänderung wurde wirksam.'
            : "{$anzahl} Preisänderungen wurden wirksam.");

        return self::SUCCESS;
    }
}
