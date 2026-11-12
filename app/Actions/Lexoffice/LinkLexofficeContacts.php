<?php

namespace App\Actions\Lexoffice;

use App\Models\Customer;
use App\Models\LexofficeRecurringTemplate;
use App\Support\Lexoffice\MatchText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verknuepft Lexoffice-Kontakte mit Portal-Kunden (§57).
 *
 * Eindeutige Treffer ueber den Namen werden automatisch verknuepft,
 * mehrdeutige bleiben offen und werden von Hand ausgewaehlt. Eine bestehende
 * Verknuepfung wird nie automatisch geaendert.
 */
class LinkLexofficeContacts
{
    /**
     * Verknuepft, was eindeutig ist, und gibt die Zahl neuer Verknuepfungen zurueck.
     */
    public function handle(): int
    {
        $linked = 0;

        $openContacts = LexofficeRecurringTemplate::query()
            ->whereNotNull('lexoffice_contact_id')
            ->whereNotIn('lexoffice_contact_id', Customer::query()->whereNotNull('lexoffice_contact_id')->select('lexoffice_contact_id'))
            ->get(['lexoffice_contact_id', 'contact_name'])
            ->unique('lexoffice_contact_id');

        if ($openContacts->isNotEmpty()) {
            $customersByName = Customer::query()
                ->whereNull('lexoffice_contact_id')
                ->get()
                ->groupBy(fn (Customer $customer): string => MatchText::normalize($customer->displayName()));

            // Zwei Lexoffice-Kontakte mit demselben Namen sind ebenso
            // mehrdeutig wie zwei Kunden mit demselben Namen.
            $contactsByName = $openContacts->groupBy(fn (LexofficeRecurringTemplate $template): string => MatchText::normalize($template->contact_name));

            foreach ($contactsByName as $name => $contacts) {
                $candidates = $customersByName->get($name);

                if ($name === '' || $contacts->count() !== 1 || $candidates === null || $candidates->count() !== 1) {
                    continue;
                }

                $candidates->first()->update(['lexoffice_contact_id' => $contacts->first()->lexoffice_contact_id]);
                $linked++;
            }
        }

        $this->refreshTemplateCustomers();

        return $linked;
    }

    /**
     * Verknuepft einen Lexoffice-Kontakt von Hand mit einem Kunden oder loest ihn.
     */
    public function link(string $contactId, ?Customer $customer): void
    {
        if ($customer !== null && filled($customer->lexoffice_contact_id) && $customer->lexoffice_contact_id !== $contactId) {
            throw ValidationException::withMessages([
                'contactLinks.'.$contactId => "{$customer->displayName()} ist bereits mit einem anderen Lexoffice-Kontakt verknüpft.",
            ]);
        }

        DB::transaction(function () use ($contactId, $customer): void {
            Customer::query()
                ->where('lexoffice_contact_id', $contactId)
                ->when($customer !== null, fn ($query) => $query->whereKeyNot($customer->getKey()))
                ->get()
                ->each(fn (Customer $previous) => $previous->update(['lexoffice_contact_id' => null]));

            $customer?->update(['lexoffice_contact_id' => $contactId]);

            // Zuordnungen der Positionen gehoerten zum bisherigen Kunden.
            LexofficeRecurringTemplate::query()
                ->where('lexoffice_contact_id', $contactId)
                ->where(fn ($query) => $query->whereNull('customer_id')->orWhere('customer_id', '!=', $customer?->getKey() ?? 0))
                ->each(fn (LexofficeRecurringTemplate $template) => $template->items()->update([
                    'customer_service_id' => null,
                    'match_source' => null,
                ]));

            $this->refreshTemplateCustomers();
        });
    }

    /**
     * Die Kundenzuordnung der Vorlagen folgt immer der Verknuepfung am Kunden.
     */
    private function refreshTemplateCustomers(): void
    {
        $customerIds = Customer::query()
            ->whereNotNull('lexoffice_contact_id')
            ->pluck('id', 'lexoffice_contact_id');

        LexofficeRecurringTemplate::query()->each(function (LexofficeRecurringTemplate $template) use ($customerIds): void {
            $template->customer_id = $customerIds->get((string) $template->lexoffice_contact_id);

            if ($template->isDirty('customer_id')) {
                $template->save();
            }
        });
    }
}
