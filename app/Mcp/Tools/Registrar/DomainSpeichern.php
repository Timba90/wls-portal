<?php

namespace App\Mcp\Tools\Registrar;

use App\Actions\Registrar\CreateManualDomain;
use App\Actions\Registrar\UpdateManualDomain;
use App\Exceptions\ReadOnlyRecordException;
use App\Mcp\Tools\PortalTool;
use App\Models\Domain;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('domain-speichern')]
#[Description('Legt eine von Hand gepflegte Domain an oder ändert sie — für Domains, deren Registrar hier keine Schnittstelle hat. Der technische Stand einer importierten Domain lässt sich damit NICHT ändern: er kommt vom Anbieter, und der nächste Abgleich würde eine Eingabe hier überschreiben. Kunde und Kundenleistung setzt „bestand-zuordnen", nicht dieses Werkzeug.')]
class DomainSpeichern extends PortalTool
{
    public function __construct(
        private readonly CreateManualDomain $anlegen,
        private readonly UpdateManualDomain $aendern,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'status' => ['nullable', 'string', 'max:40'],
            'registriert_am' => ['nullable', 'date'],
            'laeuft_ab_am' => ['nullable', 'date'],
            'verlaengert_automatisch' => ['nullable', 'boolean'],
            'nameserver' => ['nullable', 'array', 'max:20'],
            'nameserver.*' => ['string', 'max:253'],
        ]);

        $name = Domain::normalizeName($eingabe['domain']);

        if (! str_contains($name, '.')) {
            return Response::error('Das ist kein Domainname: eine Endung fehlt.');
        }

        $vorhanden = Domain::query()->where('name', $name)->first();

        $attribute = [
            'name' => $name,
            'status' => $eingabe['status'] ?? ($vorhanden->status ?? 'aktiv'),
            'registered_on' => $eingabe['registriert_am'] ?? null,
            'expires_on' => $eingabe['laeuft_ab_am'] ?? null,
            'auto_renew' => (bool) ($eingabe['verlaengert_automatisch'] ?? false),
            'nameservers' => $this->nameserver($eingabe['nameserver'] ?? []),
        ];

        try {
            $domain = $vorhanden instanceof Domain
                ? ($this->aendern)($vorhanden, $attribute)
                : ($this->anlegen)($attribute);
        } catch (ReadOnlyRecordException $ausnahme) {
            return Response::error($ausnahme->getMessage());
        }

        return Response::json([
            'vorgang' => $vorhanden instanceof Domain ? 'geändert' : 'angelegt',
            'id' => $domain->id,
            'domain' => $domain->name,
            'anbieter' => $domain->provider->label(),
            'status' => $domain->status,
            'registriert_am' => $this->date($domain->registered_on),
            'laeuft_ab_am' => $this->date($domain->expires_on),
            'verlaengert_automatisch' => $domain->auto_renew,
            'nameserver' => $domain->nameservers,
            'hinweis' => 'Bei welchem Registrar die Domain liegt, gehört in eine Notiz '
                .'(„notiz-speichern" mit typ: domain) oder in ein eigenes Feld.',
        ]);
    }

    /**
     * @param  array<int, string>  $eingabe
     * @return array<int, string>
     */
    private function nameserver(array $eingabe): array
    {
        $namen = [];

        foreach ($eingabe as $name) {
            $name = Domain::normalizeName($name);

            if ($name !== '') {
                $namen[] = $name;
            }
        }

        return array_values(array_unique($namen));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()
                ->description('Der Domainname, etwa beispiel.de. Gibt es ihn schon, wird der Datensatz geändert, sonst neu angelegt.')
                ->required(),
            'status' => $schema->string()
                ->description('Eigene Angabe, Vorgabe „aktiv" — hier gibt kein Anbieter eine Schreibweise vor.'),
            'registriert_am' => $schema->string()->description('Datum als JJJJ-MM-TT.'),
            'laeuft_ab_am' => $schema->string()->description('Datum als JJJJ-MM-TT. Steht in der Liste und in den Kennzahlen.'),
            'verlaengert_automatisch' => $schema->boolean()->description('Nur zur Information; verlängert wird beim Registrar.'),
            'nameserver' => $schema->array()->description('Die Nameserver als Liste von Namen.'),
        ];
    }
}
