<?php

namespace App\Mcp\Tools\Insights;

use App\Actions\Notes\SaveNote;
use App\Enums\NoteCategory;
use App\Mcp\Tools\PortalTool;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('notiz-speichern')]
#[Description('Hinterlegt eine Notiz an einem Kunden, Ansprechpartner oder einer Kundenleistung. Ohne notiz_id entsteht ein neuer Eintrag, mit notiz_id wird ein bestehender überschrieben.')]
class NotizSpeichern extends PortalTool
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const TYPES = [
        'kunde' => Customer::class,
        'ansprechpartner' => Contact::class,
        'leistung' => CustomerService::class,
    ];

    public function __construct(private readonly SaveNote $saveNote) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'typ' => ['required', 'string', 'in:kunde,ansprechpartner,leistung'],
            'id' => ['required', 'integer'],
            'kategorie' => ['required', 'string', 'in:general,technical,billing,contract'],
            'text' => ['required', 'string'],
            'notiz_id' => ['nullable', 'integer'],
        ]);

        $klasse = self::TYPES[$eingabe['typ']];
        $datensatz = $klasse::query()->find($eingabe['id']);

        if (! $datensatz instanceof Model) {
            return Response::error(ucfirst($eingabe['typ']).' nicht gefunden.');
        }

        $bestehende = filled($eingabe['notiz_id'] ?? null)
            ? $datensatz->notes()->find($eingabe['notiz_id'])
            : null;

        if (filled($eingabe['notiz_id'] ?? null) && is_null($bestehende)) {
            return Response::error('Notiz nicht gefunden oder gehört zu einem anderen Datensatz.');
        }

        $notiz = ($this->saveNote)(
            $datensatz,
            NoteCategory::from($eingabe['kategorie']),
            $eingabe['text'],
            $request->user(),
            $bestehende,
        );

        return Response::json([
            'vorgang' => is_null($bestehende) ? 'angelegt' : 'geändert',
            'notiz_id' => $notiz->id,
            'typ' => $eingabe['typ'],
            'id' => $datensatz->getKey(),
            'kategorie' => $notiz->category->value,
            'erstellt_am' => $this->dateTime($notiz->created_at),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'typ' => $schema->string()->enum(['kunde', 'ansprechpartner', 'leistung'])
                ->description('Art des Datensatzes, an dem die Notiz hängt.')
                ->required(),
            'id' => $schema->integer()->description('Interne ID dieses Datensatzes.')->required(),
            'kategorie' => $schema->string()->enum(['general', 'technical', 'billing', 'contract'])
                ->description('general ist allgemein, technical technisch, billing abrechnungsbezogen, contract vertraglich.')
                ->required(),
            'text' => $schema->string()->description('Inhalt der Notiz.')->required(),
            'notiz_id' => $schema->integer()->description('Zum Überschreiben einer bestehenden Notiz.'),
        ];
    }
}
