<?php

namespace App\Mcp\Tools\Insights;

use App\Mcp\Tools\PortalTool;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('historie-lesen')]
#[Description('Liest die Änderungshistorie — wahlweise zu einem bestimmten Datensatz oder als Gesamtstrom über alle Bereiche. Die Einträge sind unveränderlich und lassen sich weder ändern noch entfernen.')]
#[IsReadOnly]
class HistorieLesen extends PortalTool
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const TYPES = [
        'kunde' => Customer::class,
        'ansprechpartner' => Contact::class,
        'produkt' => Product::class,
        'leistung' => CustomerService::class,
    ];

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'typ' => ['nullable', 'string', 'in:kunde,ansprechpartner,produkt,leistung'],
            'id' => ['nullable', 'integer'],
            'ereignis' => ['nullable', 'string', 'in:created,updated,archived,restored,deleted,attached,detached'],
            'benutzer_id' => ['nullable', 'integer'],
            'anzahl' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (filled($eingabe['typ'] ?? null) !== filled($eingabe['id'] ?? null)) {
            return Response::error('„typ" und „id" gehören zusammen — bitte beide oder keines angeben.');
        }

        $query = AuditLog::query()->with('user');

        if (filled($eingabe['typ'] ?? null)) {
            $klasse = self::TYPES[$eingabe['typ']];

            $query->where('auditable_type', (new $klasse)->getMorphClass())
                ->where('auditable_id', $eingabe['id']);
        }

        foreach (['ereignis' => 'event', 'benutzer_id' => 'user_id'] as $feld => $spalte) {
            if (filled($eingabe[$feld] ?? null)) {
                $query->where($spalte, $eingabe[$feld]);
            }
        }

        $eintraege = $query->orderByDesc('id')
            ->limit($this->limit($eingabe['anzahl'] ?? null))
            ->get();

        return Response::json([
            'anzahl' => $eintraege->count(),
            'eintraege' => $eintraege->map(fn (AuditLog $eintrag): array => [
                'id' => $eintrag->id,
                'zeitpunkt' => $this->dateTime($eintrag->created_at),
                'ereignis' => $eintrag->event->value,
                'datensatz_typ' => class_basename($eintrag->auditable_type),
                'datensatz_id' => $eintrag->auditable_id,
                'benutzer' => $eintrag->user?->name,
                'ip_adresse' => $eintrag->ip_address,
                'aenderungen' => $eintrag->changes(),
                'beschreibung' => $eintrag->description,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'typ' => $schema->string()->enum(['kunde', 'ansprechpartner', 'produkt', 'leistung'])
                ->description('Nur zusammen mit „id". Ohne beides kommt der Gesamtstrom.'),
            'id' => $schema->integer()->description('Interne ID des Datensatzes. Nur zusammen mit „typ".'),
            'ereignis' => $schema->string()
                ->enum(['created', 'updated', 'archived', 'restored', 'deleted', 'attached', 'detached'])
                ->description('Auf eine Art von Ereignis einschränken.'),
            'benutzer_id' => $schema->integer()->description('Nur Änderungen dieses Benutzers.'),
            'anzahl' => $schema->integer()->description('Höchstzahl der Einträge, Standard 25, Maximum 100.'),
        ];
    }
}
