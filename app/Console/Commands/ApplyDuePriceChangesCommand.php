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
        ['applied' => $wirksam, 'lapsed' => $verfallen] = $applyDuePriceChanges();

        $this->info($wirksam === 1
            ? '1 Preisänderung wurde wirksam.'
            : "{$wirksam} Preisänderungen wurden wirksam.");

        if ($verfallen > 0) {
            $this->warn($verfallen === 1
                ? '1 Preisänderung ist verfallen, weil die Leistung archiviert war.'
                : "{$verfallen} Preisänderungen sind verfallen, weil die Leistung archiviert war.");
        }

        return self::SUCCESS;
    }
}
