<?php

namespace App\Mcp\Tools\Customers;

use App\Actions\Customers\ArchiveCustomer;
use App\Actions\Customers\RestoreCustomer;
use App\Mcp\Tools\PortalTool;
use App\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('kunde-archivieren')]
#[Description('Archiviert einen Kunden oder hebt die Archivierung wieder auf. Die Daten bleiben dabei vollständig erhalten.')]
#[IsIdempotent]
class KundeArchivieren extends PortalTool
{
    public function __construct(
        private readonly ArchiveCustomer $archiveCustomer,
        private readonly RestoreCustomer $restoreCustomer,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'archivieren' => ['nullable', 'boolean'],
        ]);

        $kunde = Customer::query()->find($eingabe['id']);

        if (! $kunde instanceof Customer) {
            return Response::error('Kunde nicht gefunden.');
        }

        $archivieren = $eingabe['archivieren'] ?? true;

        $kunde = $archivieren
            ? ($this->archiveCustomer)($kunde)
            : ($this->restoreCustomer)($kunde);

        return Response::json([
            'id' => $kunde->id,
            'kundennummer' => $kunde->customer_number,
            'status' => $kunde->status->value,
            'archiviert_am' => $this->dateTime($kunde->archived_at),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID des Kunden.')->required(),
            'archivieren' => $schema->boolean()
                ->description('true archiviert, false hebt die Archivierung auf. Standard ist true.'),
        ];
    }
}
