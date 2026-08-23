<?php

namespace App\Mcp\Tools\Pricing;

use App\Enums\PriceType;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('preis-direkt-setzen')]
#[Description('Überschreibt den Preis einer Kundenleistung unmittelbar, ohne Eintrag im Preisverlauf. Der bisherige Preis ist danach nicht mehr nachvollziehbar. Der reguläre Weg ist preisaenderung-planen; dieses Werkzeug ist für Korrekturen falsch erfasster Preise gedacht und verlangt deshalb eine Bestätigung.')]
#[IsDestructive]
class PreisDirektSetzen extends PortalTool
{
    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'leistung_id' => ['required', 'integer'],
            'preisart' => ['required', 'string', 'in:sales,purchase'],
            'preis_cents' => ['required', 'integer', 'min:0'],
            'bestaetigung' => ['required', 'string'],
        ]);

        $leistung = CustomerService::query()->find($eingabe['leistung_id']);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        if ($eingabe['bestaetigung'] !== 'ohne-preisverlauf') {
            return Response::error(
                'Bestätigung stimmt nicht. Erwartet wird die Zeichenkette „ohne-preisverlauf", '
                .'weil dieser Vorgang den bisherigen Preis spurlos ersetzt.'
            );
        }

        $preisart = PriceType::from($eingabe['preisart']);
        $spalte = $preisart->column();
        $vorher = $leistung->{$spalte};

        // Bewusst am Preisverlauf vorbei: forceFill umgeht die Actions, der
        // Schreibschutz archivierter Leistungen bleibt dennoch bestehen, weil
        // er im Model selbst sitzt.
        $leistung->forceFill([$spalte => $eingabe['preis_cents']])->save();

        return Response::json([
            'leistung_id' => $leistung->id,
            'name' => $leistung->name,
            'preisart' => $preisart->value,
            'vorher' => $this->money((int) $vorher),
            'nachher' => $this->money($eingabe['preis_cents']),
            'im_preisverlauf_vermerkt' => false,
            'marge' => $this->money($leistung->margin()->cents),
            'hinweis' => 'Der vorherige Preis ist nur noch in der Änderungshistorie zu finden, nicht im Preisverlauf.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'leistung_id' => $schema->integer()->description('Interne ID der Kundenleistung.')->required(),
            'preisart' => $schema->string()->enum(['sales', 'purchase'])
                ->description('sales ist der Verkaufspreis, purchase der Einkaufspreis.')
                ->required(),
            'preis_cents' => $schema->integer()->description('Neuer Preis in ganzen Cent.')->required(),
            'bestaetigung' => $schema->string()
                ->description('Die Zeichenkette „ohne-preisverlauf" als bewusste Bestätigung.')
                ->required(),
        ];
    }
}
