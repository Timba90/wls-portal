<?php

namespace App\Actions\Registrar;

use App\Enums\RegistrarProvider;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;

/**
 * Legt eine Domain an, die von Hand gepflegt wird.
 *
 * §60 verlangt es: „Domains anderer Provider muessen ebenfalls manuell
 * verwaltbar sein." Gemeint sind Domains, deren Registrar hier keine
 * Schnittstelle hat. Ohne diesen Weg fehlten sie im Bestand ganz — obwohl ihr
 * Ablaufdatum genauso zaehlt und der Kunde dieselbe Rechnung bekommt.
 *
 * Der Anbieter ist deshalb `Manual` und nicht der Name des Registrars: die
 * Angabe steuert, ob es einen Anschluss gibt, und zu diesen Domains gibt es
 * keinen. Bei wem sie tatsaechlich liegen, gehoert in eine Notiz oder ein
 * eigenes Feld — dort steht es als Text, wo es hingehoert, statt als Fall in
 * einem Enum, das Anschluesse benennt.
 *
 * @phpstan-type ManualDomainInput array{
 *     name: string,
 *     status?: ?string,
 *     registered_on?: ?string,
 *     expires_on?: ?string,
 *     auto_renew?: bool,
 *     nameservers?: array<int, string>,
 * }
 */
class CreateManualDomain
{
    /**
     * @param  ManualDomainInput  $attributes
     */
    public function __invoke(array $attributes): Domain
    {
        return DB::transaction(function () use ($attributes): Domain {
            $domain = new Domain;

            $domain->fill([
                'name' => Domain::normalizeName($attributes['name']),
                'provider' => RegistrarProvider::Manual,
                'status' => $attributes['status'] ?? 'aktiv',
                'registered_on' => $attributes['registered_on'] ?? null,
                'expires_on' => $attributes['expires_on'] ?? null,
                'auto_renew' => $attributes['auto_renew'] ?? false,
                'nameservers' => $attributes['nameservers'] ?? [],
                // `synced_at` bleibt leer: abgeglichen wurde hier nichts, und
                // ein Datum zu setzen hiesse, es habe einen Anbieter gegeben,
                // der geantwortet hat.
            ]);

            $domain->save();

            return $domain;
        });
    }
}
