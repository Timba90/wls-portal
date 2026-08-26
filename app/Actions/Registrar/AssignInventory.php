<?php

namespace App\Actions\Registrar;

use App\Models\Certificate;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Domain;
use InvalidArgumentException;

/**
 * Ordnet eine Domain oder ein Zertifikat einem Kunden zu.
 *
 * Die andere Haelfte des Imports: der Registrar liefert den technischen
 * Bestand, wem er gehoert weiss nur das Portal. Diese Zuordnung ist deshalb
 * von Hand gesetzt — und der Import wirft sie nicht wieder weg.
 *
 * Die Kundenleistung ist die Verbindung zur Abrechnung und bleibt freiwillig:
 * nicht jede Domain wird einzeln berechnet, manche laeuft in einem Paket mit.
 */
class AssignInventory
{
    public function __invoke(
        Domain|Certificate $eintrag,
        ?Customer $kunde,
        ?CustomerService $leistung = null,
    ): Domain|Certificate {
        if ($kunde === null && $leistung !== null) {
            throw new InvalidArgumentException(
                'Eine Kundenleistung ohne Kunde ergibt keine Zuordnung.',
            );
        }

        if ($leistung !== null && $leistung->customer_id !== $kunde?->id) {
            throw new InvalidArgumentException(
                'Die Kundenleistung gehört einem anderen Kunden.',
            );
        }

        $eintrag->forceFill([
            'customer_id' => $kunde?->id,
            // Ohne Kunde kann auch keine Leistung stehen bleiben.
            'customer_service_id' => $kunde === null ? null : $leistung?->id,
        ])->save();

        return $eintrag;
    }
}
