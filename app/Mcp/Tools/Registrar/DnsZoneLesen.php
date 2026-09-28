<?php

namespace App\Mcp\Tools\Registrar;

use App\Actions\Registrar\ReadDnsZone;
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

#[Name('dns-zone-lesen')]
#[Description('Liest die DNS-Zone einer Domain beim Anbieter — Einträge, SOA und Nameserver. Nur lesend. Die Kennung je Eintrag („kennung") ist bei ResellerInterface der Anker für eine Änderung; „anzahl_gleicher_stelle" zeigt, wo mehrere Einträge denselben Namen und Typ haben und ein Eintrag deshalb nur mit „alt_inhalt" bestimmbar ist.')]
#[IsReadOnly]
class DnsZoneLesen extends PortalTool
{
    public function __construct(private readonly ReadDnsZone $zoneLesen) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'typ' => ['nullable', 'string', 'max:20'],
            'neu_lesen' => ['nullable', 'boolean'],
        ]);

        $domain = Domain::query()->where('name', Domain::normalizeName($eingabe['domain']))->first();

        if (! $domain instanceof Domain) {
            return Response::error(
                'Diese Domain steht nicht im Bestand. Ohne Datensatz ist auch kein Anbieter bekannt, '
                .'bei dem die Zone liegt.',
            );
        }

        try {
            $zone = ($this->zoneLesen)($domain, (bool) ($eingabe['neu_lesen'] ?? false));
        } catch (RegistrarException $fehler) {
            return Response::error($fehler->getMessage());
        }

        $typ = filled($eingabe['typ'] ?? null) ? mb_strtoupper((string) $eingabe['typ']) : null;

        $records = array_values(array_filter(
            $zone->sortedRecords(),
            fn (DnsRecord $record): bool => $typ === null || $record->type === $typ,
        ));

        return Response::json([
            'domain' => $domain->name,
            'anbieter' => $domain->provider->label(),
            'zone' => $zone->origin,
            'nameserver' => $zone->nameservers,
            'verwaltender_nameserver' => $zone->nameServer,
            'soa_mail' => $zone->soaEmail,
            'ttl' => $zone->ttl,
            'zuletzt_geaendert' => $this->dateTime($zone->updatedAt),
            'anzahl_je_typ' => $zone->countsByType(),
            'eintraege' => array_map(fn (DnsRecord $record): array => [
                'name' => $record->name,
                'typ' => $record->type,
                'inhalt' => $record->content,
                'ttl' => $record->ttl,
                'prioritaet' => $record->priority,
                'kennung' => $record->reference,
                'abgeleitet' => $record->derived,
                'anzahl_gleicher_stelle' => count($zone->recordsAt($record->name, $record->type)),
            ], $records),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Der Domainname, etwa beispiel.de. Die Domain muss im Bestand stehen.')->required(),
            'typ' => $schema->string()->description('Nur Einträge dieses Typs zeigen, etwa TXT oder MX. Weglassen zeigt alle.'),
            'neu_lesen' => $schema->boolean()->description('Den vorgehaltenen Stand verwerfen und beim Anbieter neu lesen.'),
        ];
    }
}
