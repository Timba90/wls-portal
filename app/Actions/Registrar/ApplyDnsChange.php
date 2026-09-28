<?php

namespace App\Actions\Registrar;

use App\Enums\DnsChangeOperation;
use App\Models\DnsChange;
use App\Models\Domain;
use App\Support\Registrar\DnsChangePlan;
use App\Support\Registrar\DnsRecord;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use App\Support\Registrar\ZoneWriter;

/**
 * Fuehrt eine geplante DNS-Aenderung aus.
 *
 * Der zweite der zwei Schritte, und der einzige Ort im Portal, an dem eine
 * fremde Zone veraendert wird. Der Ablauf ist absichtlich umstaendlich:
 *
 * 1. Die Zone wird **frisch** gelesen — nicht aus dem Zwischenspeicher. Sonst
 *    verglich die Pruefsumme gegen eine Kopie und nicht gegen die Wirklichkeit.
 * 2. Der Plan wird aus derselben Eingabe neu gerechnet und seine Pruefsumme mit
 *    der mitgeschickten verglichen. Weichen sie ab, hat sich an genau dieser
 *    Stelle der Zone etwas bewegt: Abbruch, ohne zu schreiben.
 * 3. Geschrieben wird ein einzelner Eintrag.
 * 4. Danach wird die Zone erneut gelesen und nachgesehen, ob dort jetzt steht,
 *    was bestellt war. Diese Kontrolle ist kein Luxus: nimmt ein Anbieter das
 *    Entfernen stillschweigend nicht an und legt nur den neuen Eintrag an,
 *    stehen zwei SPF-Eintraege in der Zone — und damit gilt keiner.
 * 5. Der Vorgang wird protokolliert, auch wenn Schritt 4 nicht aufgeht.
 */
class ApplyDnsChange
{
    public function __construct(
        private readonly PlanDnsChange $planen,
        private readonly ReadDnsZone $zoneLesen,
        private readonly RegistrarClientFactory $factory,
    ) {}

    /**
     * @param  array<string, mixed>  $eingabe
     * @param  string  $pruefsumme  Die Pruefsumme aus dem Plan.
     *
     * @throws RegistrarException
     */
    public function __invoke(Domain $domain, array $eingabe, string $pruefsumme): DnsChange
    {
        $client = $this->factory->for($domain->provider);

        if (! $client instanceof ZoneWriter || ! $client->canWriteZone()) {
            throw new RegistrarException(sprintf(
                'Für %s sind schreibende DNS-Änderungen nicht möglich.',
                $domain->provider->label(),
            ));
        }

        // Frisch gelesen: die Pruefsumme soll gegen die Zone pruefen, nicht
        // gegen einen zehn Minuten alten Stand.
        $plan = ($this->planen)($domain, $eingabe, frisch: true);

        if (! hash_equals($plan->fingerprint, trim($pruefsumme))) {
            throw new RegistrarException(sprintf(
                'Die Prüfsumme passt nicht zum aktuellen Stand der Zone von %s. An „%s" (%s) hat sich etwas '
                .'geändert, seit der Plan erstellt wurde — oder die Eingabe ist eine andere. Bitte neu planen '
                .'und den neuen Stand ansehen. Es wurde nichts geändert.',
                $plan->zone->origin,
                $plan->recordName,
                $plan->recordType,
            ));
        }

        $client->applyZoneChange($plan->zone, $plan->before, $plan->after);

        [$bestaetigt, $hinweis] = $this->verify($domain, $plan);

        return DnsChange::query()->create([
            'domain_id' => $domain->getKey(),
            'domain_name' => $domain->name,
            'provider' => $domain->provider,
            'operation' => $plan->operation->value,
            'record_name' => $plan->recordName,
            'record_type' => $plan->recordType,
            'before' => $plan->before?->describe(),
            'after' => $plan->after?->describe(),
            'fingerprint' => $plan->fingerprint,
            'verified' => $bestaetigt,
            'note' => $hinweis,
            'user_id' => auth()->id(),
            'applied_at' => now(),
        ]);
    }

    /**
     * Sieht nach, ob die Zone danach zeigt, was bestellt war.
     *
     * @return array{0: bool, 1: string|null}
     */
    private function verify(Domain $domain, DnsChangePlan $plan): array
    {
        try {
            $zone = ($this->zoneLesen)($domain, true);
        } catch (RegistrarException $fehler) {
            // Die Aenderung ist durch; nur das Nachsehen ging nicht. Das ist
            // ein Hinweis, kein Abbruch — sonst stuende im Protokoll ein
            // Fehlschlag, wo etwas geaendert wurde.
            return [false, 'Nach der Änderung ließ sich die Zone nicht erneut lesen: '.$fehler->getMessage()];
        }

        $jetzt = $zone->recordsAt($plan->recordName, $plan->recordType);

        $neuDa = $plan->after === null || $this->enthaelt($jetzt, $plan->after);
        $altWeg = $plan->before === null
            || $plan->operation === DnsChangeOperation::Anlegen
            || ! $this->enthaelt($jetzt, $plan->before);

        if ($neuDa && $altWeg) {
            return [true, null];
        }

        $hinweise = [];

        if (! $neuDa) {
            $hinweise[] = 'Der neue Eintrag steht nach der Änderung nicht in der Zone.';
        }

        if (! $altWeg) {
            $hinweise[] = 'Der alte Eintrag steht noch in der Zone — er ist womöglich doppelt vorhanden.';
        }

        $hinweise[] = 'Jetzt in der Zone: '.($jetzt === []
            ? 'kein Eintrag an dieser Stelle'
            : implode('; ', array_map(fn (DnsRecord $record): string => $record->describe(), $jetzt)));

        return [false, implode(' ', $hinweise)];
    }

    /**
     * @param  array<int, DnsRecord>  $records
     */
    private function enthaelt(array $records, DnsRecord $gesucht): bool
    {
        foreach ($records as $record) {
            if ($this->normalisieren($record->content) === $this->normalisieren($gesucht->content)) {
                return true;
            }
        }

        return false;
    }

    private function normalisieren(string $wert): string
    {
        $wert = trim($wert);

        if (str_starts_with($wert, '"') && str_ends_with($wert, '"') && mb_strlen($wert) > 1) {
            $wert = mb_substr($wert, 1, -1);
        }

        return (string) preg_replace('/\s+/', ' ', trim($wert));
    }
}
