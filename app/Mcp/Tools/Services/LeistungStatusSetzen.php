<?php

namespace App\Mcp\Tools\Services;

use App\Actions\Services\ArchiveCustomerService;
use App\Actions\Services\ChangeCustomerServiceStatus;
use App\Actions\Services\RestoreCustomerService;
use App\Actions\Services\SetDoNotBill;
use App\Enums\CustomerServiceStatus;
use App\Enums\DoNotBillReason;
use App\Mcp\Tools\PortalTool;
use App\Models\CustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('leistung-status-setzen')]
#[Description('Ändert den Status einer Kundenleistung — geplant, aktiv, pausiert, beendet oder archiviert — und setzt oder löst die Kennzeichnung „bewusst nicht abrechnen". Pausierte und nicht abgerechnete Leistungen zählen nicht zum Soll-Umsatz.')]
class LeistungStatusSetzen extends PortalTool
{
    public function __construct(
        private readonly ChangeCustomerServiceStatus $changeStatus,
        private readonly ArchiveCustomerService $archiveService,
        private readonly RestoreCustomerService $restoreService,
        private readonly SetDoNotBill $setDoNotBill,
    ) {}

    public function handle(Request $request): Response
    {
        $eingabe = $request->validate([
            'id' => ['required', 'integer'],
            'status' => ['nullable', 'string', 'in:planned,active,paused,ended,archived'],
            'nicht_abrechnen' => ['nullable', 'boolean'],
            'nicht_abrechnen_grund' => ['nullable', 'string', 'in:included,goodwill,own_service,free'],
        ]);

        $leistung = CustomerService::query()->find($eingabe['id']);

        if (! $leistung instanceof CustomerService) {
            return Response::error('Kundenleistung nicht gefunden.');
        }

        if (blank($eingabe['status'] ?? null) && is_null($eingabe['nicht_abrechnen'] ?? null)) {
            return Response::error('Bitte „status" oder „nicht_abrechnen" angeben.');
        }

        // Der Status wird zuerst gesetzt: eine archivierte Leistung ist
        // schreibgeschuetzt, ihre Reaktivierung muss der Kennzeichnung
        // vorausgehen.
        if (filled($eingabe['status'] ?? null)) {
            $leistung = $this->applyStatus($leistung, CustomerServiceStatus::from($eingabe['status']));
        }

        if (! is_null($eingabe['nicht_abrechnen'] ?? null)) {
            if ($eingabe['nicht_abrechnen']) {
                if (blank($eingabe['nicht_abrechnen_grund'] ?? null)) {
                    return Response::error(
                        'Für „bewusst nicht abrechnen" ist ein Grund erforderlich: included, goodwill, own_service oder free.'
                    );
                }

                $leistung = $this->setDoNotBill->mark(
                    $leistung,
                    DoNotBillReason::from($eingabe['nicht_abrechnen_grund']),
                );
            } else {
                $leistung = $this->setDoNotBill->release($leistung);
            }
        }

        return Response::json([
            'id' => $leistung->id,
            'name' => $leistung->name,
            'status' => $leistung->status->value,
            'nicht_abrechnen' => $leistung->do_not_bill,
            'nicht_abrechnen_grund' => $leistung->do_not_bill_reason?->value,
            'zaehlt_zum_umsatz' => $leistung->countsTowardsRevenue(),
            'umsatz_monat' => $this->money($leistung->monthlyRevenue()->cents),
        ]);
    }

    private function applyStatus(CustomerService $leistung, CustomerServiceStatus $status): CustomerService
    {
        if ($status === CustomerServiceStatus::Archived) {
            return ($this->archiveService)($leistung);
        }

        if ($leistung->isArchived()) {
            $leistung = ($this->restoreService)($leistung);
        }

        return $leistung->status === $status
            ? $leistung
            : ($this->changeStatus)($leistung, $status);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Interne ID der Kundenleistung.')->required(),
            'status' => $schema->string()
                ->enum(['planned', 'active', 'paused', 'ended', 'archived'])
                ->description('Archivierte Leistungen werden durch jeden anderen Status wieder reaktiviert.'),
            'nicht_abrechnen' => $schema->boolean()
                ->description('true kennzeichnet die Leistung als bewusst nicht abzurechnen, false hebt das auf.'),
            'nicht_abrechnen_grund' => $schema->string()
                ->enum(['included', 'goodwill', 'own_service', 'free'])
                ->description('Pflicht, wenn nicht_abrechnen auf true gesetzt wird.'),
        ];
    }
}
