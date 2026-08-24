<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\ArchiveCustomer;
use App\Actions\Customers\RestoreCustomer;
use App\Exceptions\ArchivingNotPossibleException;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kundendetailseite — die wichtigste Ansicht der Anwendung.
 *
 * Die Bereiche Ansprechpartner, Leistungen, Notizen, Dokumente und Historie
 * werden in den folgenden Meilensteinen befuellt.
 */
#[Layout('components.layouts.app')]
class CustomerDetail extends Component
{
    public Customer $customer;

    #[Url(as: 'bereich', except: 'uebersicht')]
    public string $tab = 'leistungen';

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function archive(ArchiveCustomer $archiveCustomer): void
    {
        try {
            $archiveCustomer($this->customer);
        } catch (ArchivingNotPossibleException $exception) {
            $this->dispatch('archivierung-nicht-moeglich', meldung: $exception->getMessage());

            return;
        }

        $this->customer->refresh();

        $this->dispatch('kunde-archiviert');
    }

    public function restore(RestoreCustomer $restoreCustomer): void
    {
        $restoreCustomer($this->customer);

        $this->customer->refresh();

        $this->dispatch('kunde-reaktiviert');
    }

    /**
     * Stammdaten fuer die rechte Spalte, in der Reihenfolge des Entwurfs.
     *
     * @return array<string, ?string>
     */
    public function masterData(): array
    {
        $gemeinsam = [
            'Kundennummer' => $this->customer->customer_number,
            'Typ' => $this->customer->type->label(),
        ];

        $persoenlich = $this->customer->isCompany()
            ? ['Firmenname' => $this->customer->company_name]
            : [
                'Anrede' => $this->customer->salutation?->label(),
                'Akademischer Titel' => $this->customer->academic_title,
                'Vorname' => $this->customer->first_name,
                'Nachname' => $this->customer->last_name,
                'Geburtsdatum' => $this->customer->birth_date?->format('d.m.Y'),
                'Geschlecht' => $this->customer->gender?->label(),
            ];

        return [
            ...$gemeinsam,
            ...$persoenlich,
            'Kurzbezeichnung' => $this->customer->short_label,
            'Internes Kürzel' => $this->customer->internal_code,
            'Verantwortlich' => $this->customer->responsibleUser?->name,
            'Angelegt' => $this->customer->created_at?->format('d.m.Y'),
            'Zuletzt geändert' => $this->customer->updated_at?->format('d.m.Y H:i'),
            ...($this->customer->archived_at
                ? ['Archiviert' => $this->customer->archived_at->format('d.m.Y H:i')]
                : []),
        ];
    }

    public function render(): View
    {
        // Fuer die Kennzahlen im Kopfband: nur abrechnungsrelevante Leistungen.
        $this->customer->load([
            'responsibleUser',
            'services' => fn ($query) => $query->billable(),
            'emailAddresses',
            'phoneNumbers',
            'contactAssignments.contact.emailAddresses',
            'contactAssignments.roles',
        ]);

        return view('livewire.customers.customer-detail', [
            'activeServices' => $this->customer->services()->active()->count(),
        ])->title($this->customer->displayName());
    }
}
