<?php

namespace App\Mcp\Tools\Registrar;

use App\Actions\Registrar\PlanDnsChange;
use App\Enums\DnsChangeOperation;
use App\Mcp\Tools\PortalTool;
use App\Models\Domain;
use App\Support\Registrar\DnsRecord;
use App\Support\Registrar\RegistrarException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('dns-aenderung-planen')]
#[Description('Plant eine Änderung an einem DNS-Eintrag, ohne etwas zu ändern: liest die Zone, bestimmt den gemeinten Eintrag und zeigt Ist und Soll samt einer Prüfsumme. Nur mit dieser Prüfsumme führt „dns-aenderung-anwenden" die Änderung aus. Liegen an einer Stelle mehrere Einträge desselben Typs (etwa SPF neben einem Bestätigungs-Token), muss „alt_inhalt" sagen, welcher gemeint ist — sonst bricht die Planung mit der Liste der vorhandenen ab.')]
#[IsReadOnly]
class DnsAenderungPlanen extends PortalTool
{
    public function __construct(private readonly PlanDnsChange $planen) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'vorgang' => ['required', 'string', 'in:anlegen,aendern,loeschen'],
            'name' => ['nullable', 'string', 'max:253'],
            'typ' => ['required', 'string', 'max:20'],
            'inhalt' => ['nullable', 'string', 'max:4000'],
            'alt_inhalt' => ['nullable', 'string', 'max:4000'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:604800'],
            'prioritaet' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $domain = Domain::query()->where('name', Domain::normalizeName($eingabe['domain']))->first();

        if (! $domain instanceof Domain) {
            return Response::error(
                'Diese Domain steht nicht im Bestand. Ohne Datensatz ist auch kein Anbieter bekannt, '
                .'bei dem die Zone liegt.',
            );
        }

        try {
            $plan = ($this->planen)($domain, $eingabe, frisch: true);
        } catch (RegistrarException $fehler) {
            return Response::error($fehler->getMessage());
        }

        return Response::json([
            'domain' => $domain->name,
            'anbieter' => $domain->provider->label(),
            'vorgang' => $plan->operation->value,
            'was_passiert' => $plan->operation->label(),
            'eintrag' => ['name' => $plan->recordName, 'typ' => $plan->recordType],
            'ist' => $plan->before === null ? null : $this->record($plan->before),
            'soll' => $plan->after === null ? null : $this->record($plan->after),
            'zusammenfassung' => $plan->describe(),
            'an_dieser_stelle_vorhanden' => array_map(
                fn (DnsRecord $record): array => $this->record($record),
                $plan->siblings,
            ),
            'pruefsumme' => $plan->fingerprint,
            'hinweis' => 'Die Prüfsumme gilt für genau diesen Stand. Ändert sich an dieser Stelle der Zone etwas, '
                .'lehnt „dns-aenderung-anwenden" ab und es muss neu geplant werden.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function record(DnsRecord $record): array
    {
        return [
            'name' => $record->name,
            'typ' => $record->type,
            'inhalt' => $record->content,
            'ttl' => $record->ttl,
            'prioritaet' => $record->priority,
            'kennung' => $record->reference,
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Der Domainname, etwa beispiel.de. Die Domain muss im Bestand stehen.')->required(),
            'vorgang' => $schema->string()
                ->enum(DnsChangeOperation::values())
                ->description('anlegen für einen neuen Eintrag, aendern für einen bestehenden, loeschen zum Entfernen.')
                ->required(),
            'typ' => $schema->string()->description('Der Eintragstyp, etwa TXT, A, AAAA, CNAME oder MX.')->required(),
            'name' => $schema->string()
                ->description('Der Name relativ zur Domain, etwa „_dmarc" oder „mail._domainkey". Weglassen oder „@" meint die Domain selbst.'),
            'inhalt' => $schema->string()
                ->description('Der Wert, der danach im Eintrag stehen soll. Bei anlegen und aendern nötig. Wird unverändert geschrieben — hier wird nichts zusammengerechnet.'),
            'alt_inhalt' => $schema->string()
                ->description('Der Wert, der jetzt im Eintrag steht. Nötig, wenn an derselben Stelle mehrere Einträge desselben Typs liegen; sonst freiwillig.'),
            'ttl' => $schema->integer()->description('Gültigkeitsdauer in Sekunden. Weglassen behält die bisherige beziehungsweise nimmt die Vorgabe der Zone.'),
            'prioritaet' => $schema->integer()->description('Nur bei MX und SRV sinnvoll.'),
        ];
    }
}
