<?php

namespace App\Mcp\Tools\Registrar;

use App\Mcp\Tools\PortalTool;
use App\Models\Certificate;
use App\Models\Domain;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('bestand-suchen')]
#[Description('Durchsucht die importierten Domains und Zertifikate. Lässt sich auf Ablauf und Zuordnung einschränken — „ohne_kunde" nennt den offenen Rest nach einem Import, „ohne_leistung" die Lücke zwischen Kunde und Abrechnung.')]
#[IsReadOnly]
class BestandSuchen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'typ' => ['nullable', 'string', 'in:domain,zertifikat'],
            'suchbegriff' => ['nullable', 'string', 'max:255'],
            'kunde_id' => ['nullable', 'integer'],
            'zuordnung' => ['nullable', 'string', 'in:ohne_kunde,ohne_leistung,zugeordnet'],
            'laeuft_ab_in_tagen' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'abgelaufen' => ['nullable', 'boolean'],
            'anzahl' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $typ = $eingabe['typ'] ?? null;
        $anzahl = $this->limit($eingabe['anzahl'] ?? null);

        $domains = $typ === 'zertifikat' ? collect() : $this->suchen(Domain::query(), $eingabe, 'name', $anzahl);
        $zertifikate = $typ === 'domain' ? collect() : $this->suchen(Certificate::query(), $eingabe, 'common_name', $anzahl);

        return Response::json([
            'anzahl' => $domains->count() + $zertifikate->count(),
            'domains' => $domains->map(fn (Domain $domain): array => [
                'id' => $domain->id,
                'name' => $domain->name,
                'anbieter' => $domain->provider->value,
                'status' => $domain->status,
                'laeuft_ab' => $this->date($domain->expires_on),
                'tage_bis_ablauf' => $domain->daysUntilExpiry(),
                'automatische_verlaengerung' => $domain->auto_renew,
                'kunde' => $this->kunde($domain),
                'leistung' => $this->leistung($domain),
            ])->values(),
            'zertifikate' => $zertifikate->map(fn (Certificate $zertifikat): array => [
                'id' => $zertifikat->id,
                'name' => $zertifikat->common_name,
                'anbieter' => $zertifikat->provider->value,
                'aussteller' => $zertifikat->issuer,
                'laeuft_ab' => $this->date($zertifikat->expires_on),
                'tage_bis_ablauf' => $zertifikat->daysUntilExpiry(),
                'kunde' => $this->kunde($zertifikat),
                'leistung' => $this->leistung($zertifikat),
            ])->values(),
        ]);
    }

    /**
     * @template TModel of Domain|Certificate
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $eingabe
     * @return Collection<int, TModel>
     */
    private function suchen(Builder $query, array $eingabe, string $namensspalte, int $anzahl)
    {
        $query->with(['customer', 'customerService']);

        $this->applySearch($query, $eingabe['suchbegriff'] ?? null, [$namensspalte]);

        if (filled($eingabe['kunde_id'] ?? null)) {
            $query->where('customer_id', $eingabe['kunde_id']);
        }

        match ($eingabe['zuordnung'] ?? null) {
            'ohne_kunde' => $query->unassigned(),
            'ohne_leistung' => $query->withoutService(),
            'zugeordnet' => $query->whereNotNull('customer_id'),
            default => null,
        };

        if (filled($eingabe['laeuft_ab_in_tagen'] ?? null)) {
            $query->expiringWithin((int) $eingabe['laeuft_ab_in_tagen']);
        }

        if ($eingabe['abgelaufen'] ?? false) {
            $query->whereNotNull('expires_on')->whereDate('expires_on', '<', now()->toDateString());
        }

        // Ohne Ablaufdatum ans Ende: das ist kein Termin, sondern eine Lücke.
        return $query->orderByRaw('expires_on is null')
            ->orderBy('expires_on')
            ->limit($anzahl)
            ->get();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function kunde(Domain|Certificate $eintrag): ?array
    {
        return $eintrag->customer === null ? null : [
            'id' => $eintrag->customer->id,
            'name' => $eintrag->customer->displayName(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function leistung(Domain|Certificate $eintrag): ?array
    {
        return $eintrag->customerService === null ? null : [
            'id' => $eintrag->customerService->id,
            'name' => $eintrag->customerService->billing_label ?: $eintrag->customerService->name,
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'typ' => $schema->string()
                ->enum(['domain', 'zertifikat'])
                ->description('Ohne Angabe werden beide Bestände durchsucht.'),
            'suchbegriff' => $schema->string()->description('Teil des Domainnamens beziehungsweise des Zertifikatsnamens.'),
            'kunde_id' => $schema->integer()->description('Nur den Bestand dieses Kunden.'),
            'zuordnung' => $schema->string()
                ->enum(['ohne_kunde', 'ohne_leistung', 'zugeordnet'])
                ->description('ohne_leistung meint: einem Kunden zugeordnet, aber ohne Kundenleistung — die Verbindung zur Abrechnung fehlt.'),
            'laeuft_ab_in_tagen' => $schema->integer()->description('Nur Einträge, die innerhalb dieser Frist ablaufen.'),
            'abgelaufen' => $schema->boolean()->description('true liefert nur Einträge, deren Ablaufdatum verstrichen ist.'),
            'anzahl' => $schema->integer()->description('Höchstzahl je Bestand, Voreinstellung 25, Obergrenze 100.'),
        ];
    }
}
