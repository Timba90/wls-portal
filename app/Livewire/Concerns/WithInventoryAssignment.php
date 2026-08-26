<?php

namespace App\Livewire\Concerns;

use App\Actions\Registrar\AssignInventory;
use App\Models\Certificate;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Domain;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Zuordnung eines importierten Bestands zu einem Kunden.
 *
 * Domains und Zertifikate kommen aus derselben Schnittstelle und tragen
 * dieselben beiden Felder; die Oberflaeche dafuer ist in beiden Listen
 * dieselbe und steht deshalb hier.
 */
trait WithInventoryAssignment
{
    public bool $showAssignmentForm = false;

    public ?int $assigningId = null;

    public ?string $assignmentCustomerId = null;

    public ?string $assignmentServiceId = null;

    /**
     * Der Datensatz, um den es geht — Domain oder Zertifikat.
     *
     * @return class-string<Domain|Certificate>
     */
    abstract protected function inventoryModel(): string;

    public function startAssignment(int $id): void
    {
        $eintrag = $this->inventoryModel()::query()->findOrFail($id);

        $this->assigningId = $eintrag->getKey();
        $this->assignmentCustomerId = $eintrag->customer_id === null ? null : (string) $eintrag->customer_id;
        $this->assignmentServiceId = $eintrag->customer_service_id === null ? null : (string) $eintrag->customer_service_id;
        $this->showAssignmentForm = true;
    }

    /**
     * Ein Kundenwechsel macht die bisher gewaehlte Leistung ungueltig — sie
     * gehoert dem alten Kunden.
     */
    public function updatedAssignmentCustomerId(): void
    {
        $this->assignmentServiceId = null;
    }

    public function saveAssignment(AssignInventory $zuordnen): void
    {
        if ($this->assigningId === null) {
            return;
        }

        $eintrag = $this->inventoryModel()::query()->findOrFail($this->assigningId);

        $kunde = $this->assignmentCustomerId === null || $this->assignmentCustomerId === ''
            ? null
            : Customer::query()->findOrFail($this->assignmentCustomerId);

        $leistung = $kunde === null || $this->assignmentServiceId === null || $this->assignmentServiceId === ''
            ? null
            : CustomerService::query()->findOrFail($this->assignmentServiceId);

        try {
            $zuordnen($eintrag, $kunde, $leistung);
        } catch (InvalidArgumentException $ausnahme) {
            $this->addError('assignmentServiceId', $ausnahme->getMessage());

            return;
        }

        $this->closeAssignment();
        $this->dispatch('zuordnung-gespeichert');
    }

    public function closeAssignment(): void
    {
        $this->reset('showAssignmentForm', 'assigningId', 'assignmentCustomerId', 'assignmentServiceId');
        $this->resetErrorBag();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function assignableCustomers(): Collection
    {
        return Customer::query()
            ->active()
            ->orderBy('company_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Customer $kunde): array => [
                'id' => $kunde->getKey(),
                'name' => $kunde->customer_number.' · '.$kunde->displayName(),
            ]);
    }

    /**
     * Die Leistungen des gewaehlten Kunden — ohne Kunde keine Auswahl.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function assignableServices(): Collection
    {
        if ($this->assignmentCustomerId === null || $this->assignmentCustomerId === '') {
            return collect();
        }

        return CustomerService::query()
            ->where('customer_id', $this->assignmentCustomerId)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'billing_label'])
            ->map(fn (CustomerService $leistung): array => [
                'id' => $leistung->getKey(),
                'name' => $leistung->billing_label ?: $leistung->name,
            ]);
    }
}
