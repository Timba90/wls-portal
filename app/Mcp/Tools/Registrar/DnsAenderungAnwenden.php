<?php

namespace App\Mcp\Tools\Registrar;

use App\Actions\Registrar\ApplyDnsChange;
use App\Enums\DnsChangeOperation;
use App\Mcp\Tools\PortalTool;
use App\Models\Domain;
use App\Support\Registrar\RegistrarException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('dns-aenderung-anwenden')]
#[Description('Ändert einen DNS-Eintrag beim Anbieter. Verlangt die Prüfsumme aus „dns-aenderung-planen" und als Bestätigung den Domainnamen; passt die Prüfsumme nicht mehr zum Stand der Zone, wird nichts geändert. Nach dem Schreiben wird die Zone erneut gelesen und nachgesehen, ob dort steht, was bestellt war. Jede Änderung wird protokolliert. Ein falsch gesetzter Eintrag schaltet eine Kundenseite oder deren Mailempfang sofort ab.')]
#[IsDestructive]
class DnsAenderungAnwenden extends PortalTool
{
    public function __construct(private readonly ApplyDnsChange $anwenden) {}

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
            'pruefsumme' => ['required', 'string', 'size:64'],
            'bestaetigung' => ['required', 'string'],
        ]);

        $name = Domain::normalizeName($eingabe['domain']);

        $domain = Domain::query()->where('name', $name)->first();

        if (! $domain instanceof Domain) {
            return Response::error(
                'Diese Domain steht nicht im Bestand. Ohne Datensatz ist auch kein Anbieter bekannt, '
                .'bei dem die Zone liegt.',
            );
        }

        // Dieselbe Absicherung wie bei den übrigen endgültigen Vorgängen: der
        // Name muss von Hand dastehen, damit kein Aufruf aus Versehen greift.
        if (Domain::normalizeName($eingabe['bestaetigung']) !== $domain->name) {
            return Response::error(sprintf(
                'Zur Bestätigung muss „bestaetigung" den Domainnamen enthalten: %s',
                $domain->name,
            ));
        }

        try {
            $protokoll = ($this->anwenden)($domain, $eingabe, $eingabe['pruefsumme']);
        } catch (RegistrarException $fehler) {
            return Response::error($fehler->getMessage());
        }

        return Response::json([
            'domain' => $domain->name,
            'anbieter' => $domain->provider->label(),
            'vorgang' => $protokoll->operation,
            'eintrag' => ['name' => $protokoll->record_name, 'typ' => $protokoll->record_type],
            'vorher' => $protokoll->before,
            'nachher' => $protokoll->after,
            'in_der_zone_bestaetigt' => $protokoll->verified,
            'hinweis' => $protokoll->note,
            'protokoll_id' => $protokoll->id,
            'ausgefuehrt_am' => $this->dateTime($protokoll->applied_at),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Der Domainname, etwa beispiel.de.')->required(),
            'vorgang' => $schema->string()
                ->enum(DnsChangeOperation::values())
                ->description('Muss derselbe Vorgang sein wie im Plan.')
                ->required(),
            'typ' => $schema->string()->description('Der Eintragstyp, etwa TXT, A, AAAA, CNAME oder MX.')->required(),
            'pruefsumme' => $schema->string()
                ->description('Die Prüfsumme aus „dns-aenderung-planen". Sie gilt nur für den Stand, der dort gezeigt wurde.')
                ->required(),
            'bestaetigung' => $schema->string()
                ->description('Zur Sicherheit der Domainname, etwa beispiel.de.')
                ->required(),
            'name' => $schema->string()->description('Der Name relativ zur Domain. Weglassen oder „@" meint die Domain selbst.'),
            'inhalt' => $schema->string()->description('Der Wert, der danach im Eintrag stehen soll — wortgleich wie im Plan.'),
            'alt_inhalt' => $schema->string()->description('Der Wert, der jetzt im Eintrag steht — wortgleich wie im Plan.'),
            'ttl' => $schema->integer()->description('Gültigkeitsdauer in Sekunden, wie im Plan.'),
            'prioritaet' => $schema->integer()->description('Nur bei MX und SRV sinnvoll, wie im Plan.'),
        ];
    }
}
