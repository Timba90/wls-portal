<?php

namespace App\Console\Commands;

use App\Actions\Lexoffice\SyncLexofficeRecurringTemplates;
use App\Support\Lexoffice\LexofficeClient;
use App\Support\Lexoffice\LexofficeException;
use Illuminate\Console\Command;

/**
 * Der taegliche Abgleich der Serienrechnungen aus Lexoffice (§57).
 */
class SyncLexofficeRecurringTemplatesCommand extends Command
{
    protected $signature = 'lexoffice:serienrechnungen';

    protected $description = 'Liest die Serienrechnungen aus Lexoffice und gleicht sie mit den Kundenleistungen ab';

    public function handle(LexofficeClient $client, SyncLexofficeRecurringTemplates $sync): int
    {
        if (! $client->isConfigured()) {
            $this->components->info('Kein Lexoffice-API-Schlüssel hinterlegt — nichts abzugleichen.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->handle($client);
        } catch (LexofficeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%d Serienrechnungen, %d neu gelesen, %d entfernt, %d Kunden verknüpft, %d Positionen zugeordnet.',
            $result['templates'],
            $result['fetched'],
            $result['removed'],
            $result['linked_customers'],
            $result['matched_items'],
        ));

        return self::SUCCESS;
    }
}
