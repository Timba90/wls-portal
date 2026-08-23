<?php

namespace App\Actions\Pricing;

use App\Models\PriceChange;
use Illuminate\Support\Facades\DB;

/**
 * Setzt eine geplante Preisaenderung wirksam.
 *
 * Schreibt den neuen Preis auf die Kundenleistung und markiert die Aenderung
 * als angewendet. Der denormalisierte Preis auf der Leistung und der
 * Preisverlauf werden ausschliesslich hier gemeinsam fortgeschrieben.
 */
class ApplyPriceChange
{
    public function __invoke(PriceChange $change): PriceChange
    {
        if ($change->isApplied()) {
            return $change;
        }

        DB::transaction(function () use ($change): void {
            $service = $change->customerService;

            // Archivierte Leistungen bleiben unveraendert; die geplante
            // Aenderung verfaellt und bleibt als Historie erhalten.
            if ($service->isArchived()) {
                $change->forceFill([
                    'applied_at' => now(),
                    'note' => trim(($change->note ? $change->note.' — ' : '')
                        .'Nicht wirksam geworden: Leistung war zum Wirksamkeitsdatum archiviert.'),
                ])->save();

                return;
            }

            $column = $change->price_type->column();

            // Der tatsaechliche Vorgaengerpreis kann sich seit der Planung
            // geaendert haben; der Verlauf haelt den Stand zum Zeitpunkt des
            // Wirksamwerdens fest.
            $change->forceFill([
                'old_price_cents' => $service->{$column},
                'applied_at' => now(),
            ])->save();

            $service->forceFill([$column => $change->new_price_cents])->save();
        });

        return $change->refresh();
    }
}
