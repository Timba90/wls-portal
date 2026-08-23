<?php

namespace App\Livewire\Contacts;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\UpdateContact;
use App\Enums\ContactChannelType;
use App\Enums\ContactMethod;
use App\Enums\CustomerType;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formular fuer Ansprechpartner inklusive Kontaktkanaelen und
 * Kundenzuordnungen.
 */
#[Layout('components.layouts.app')]
class ContactForm extends Component
{
    public ?Contact $contact = null;

    public string $salutation = '';

    public string $academic_title = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $gender = '';

    public string $birth_date = '';

    public string $preferred_contact_method = '';

    /** @var array<int, array{email: string, type: string, is_primary: bool}> */
    public array $emails = [];

    /** @var array<int, array{number: string, type: string, is_primary: bool}> */
    public array $phones = [];

    /** @var array<int, array<string, mixed>> */
    public array $assignments = [];

    public function mount(?Contact $contact = null, ?int $customerId = null): void
    {
        if (! $contact?->exists) {
            $this->addEmail();
            $this->addPhone();
            $this->addAssignment($customerId);

            return;
        }

        $this->contact = $contact;
        $this->salutation = $contact->salutation?->value ?? '';
        $this->academic_title = (string) $contact->academic_title;
        $this->first_name = $contact->first_name;
        $this->last_name = $contact->last_name;
        $this->gender = $contact->gender?->value ?? '';
        $this->birth_date = $contact->birth_date?->format('Y-m-d') ?? '';
        $this->preferred_contact_method = $contact->preferred_contact_method?->value ?? '';

        $this->emails = $contact->emailAddresses
            ->map(fn ($email): array => [
                'email' => $email->email,
                'type' => $email->type->value,
                'is_primary' => $email->is_primary,
            ])
            ->all();

        $this->phones = $contact->phoneNumbers
            ->map(fn ($phone): array => [
                'number' => $phone->number,
                'type' => $phone->type->value,
                'is_primary' => $phone->is_primary,
            ])
            ->all();

        $this->assignments = $contact->assignments
            ->map(fn ($assignment): array => [
                'customer_id' => (string) $assignment->customer_id,
                'role_ids' => $assignment->roles->pluck('id')->all(),
                'is_primary_contact' => $assignment->is_primary_contact,
                'is_billing_contact' => $assignment->is_billing_contact,
                'is_active' => $assignment->is_active,
                'priority' => $assignment->priority,
                'preferred_contact_method' => $assignment->preferred_contact_method?->value ?? '',
                'note' => (string) $assignment->note,
            ])
            ->all();

        $this->emails === [] && $this->addEmail();
        $this->phones === [] && $this->addPhone();
        $this->assignments === [] && $this->addAssignment();
    }

    public function isEditing(): bool
    {
        return $this->contact?->exists ?? false;
    }

    public function addEmail(): void
    {
        $this->emails[] = [
            'email' => '',
            'type' => ContactChannelType::Business->value,
            'is_primary' => $this->emails === [],
        ];
    }

    public function removeEmail(int $index): void
    {
        unset($this->emails[$index]);
        $this->emails = array_values($this->emails);
    }

    public function markEmailPrimary(int $index): void
    {
        foreach ($this->emails as $key => $email) {
            $this->emails[$key]['is_primary'] = $key === $index;
        }
    }

    public function addPhone(): void
    {
        $this->phones[] = [
            'number' => '',
            'type' => ContactChannelType::Business->value,
            'is_primary' => $this->phones === [],
        ];
    }

    public function removePhone(int $index): void
    {
        unset($this->phones[$index]);
        $this->phones = array_values($this->phones);
    }

    public function markPhonePrimary(int $index): void
    {
        foreach ($this->phones as $key => $phone) {
            $this->phones[$key]['is_primary'] = $key === $index;
        }
    }

    public function addAssignment(?int $customerId = null): void
    {
        $this->assignments[] = [
            'customer_id' => $customerId ? (string) $customerId : '',
            'role_ids' => [],
            'is_primary_contact' => false,
            'is_billing_contact' => false,
            'is_active' => true,
            'priority' => 100,
            'preferred_contact_method' => '',
            'note' => '',
        ];
    }

    public function removeAssignment(int $index): void
    {
        unset($this->assignments[$index]);
        $this->assignments = array_values($this->assignments);
    }

    public function save(CreateContact $createContact, UpdateContact $updateContact): void
    {
        $validated = $this->validate($this->rules(), attributes: $this->validationAttributes());

        $attributes = [
            'salutation' => $validated['salutation'] ?: null,
            'academic_title' => $validated['academic_title'] ?: null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'gender' => $validated['gender'] ?: null,
            'birth_date' => $validated['birth_date'] ?: null,
            'preferred_contact_method' => $validated['preferred_contact_method'] ?: null,
        ];

        $assignments = collect($this->assignments)
            ->filter(fn (array $assignment): bool => filled($assignment['customer_id']))
            ->map(fn (array $assignment): array => [
                ...$assignment,
                'customer_id' => (int) $assignment['customer_id'],
                'preferred_contact_method' => $assignment['preferred_contact_method'] ?: null,
                'note' => $assignment['note'] ?: null,
            ])
            ->values()
            ->all();

        $contact = $this->isEditing()
            ? $updateContact($this->contact, $attributes, $assignments, $this->emails, $this->phones)
            : $createContact($attributes, $assignments, $this->emails, $this->phones);

        session()->flash('erfolg', $this->isEditing()
            ? 'Ansprechpartner gespeichert.'
            : 'Ansprechpartner angelegt.');

        $this->redirectRoute('contacts.show', $contact, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.contacts.contact-form', [
            'salutationOptions' => Salutation::options(),
            'genderOptions' => Gender::options(),
            'contactMethodOptions' => ContactMethod::options(),
            'emailTypeOptions' => ContactChannelType::options(ContactChannelType::forEmail()),
            'phoneTypeOptions' => ContactChannelType::options(ContactChannelType::forPhone()),
            'customers' => $this->companyCustomers(),
            'roles' => $this->roles(),
        ])->title($this->isEditing() ? 'Ansprechpartner bearbeiten' : 'Ansprechpartner anlegen');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'salutation' => ['nullable', Rule::in(Salutation::values())],
            'academic_title' => ['nullable', 'string', 'max:60'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(Gender::values())],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'preferred_contact_method' => ['nullable', Rule::in(ContactMethod::values())],
            'emails.*.email' => ['nullable', 'email', 'max:255'],
            'emails.*.type' => ['required', Rule::in(ContactChannelType::values())],
            'phones.*.number' => ['nullable', 'string', 'max:60'],
            'phones.*.type' => ['required', Rule::in(ContactChannelType::values())],
            // Ein Ansprechpartner muss mindestens einem Kunden zugeordnet sein.
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.customer_id' => ['required', 'exists:customers,id'],
            'assignments.*.role_ids' => ['array'],
            'assignments.*.role_ids.*' => ['exists:contact_roles,id'],
            'assignments.*.priority' => ['required', 'integer', 'min:1', 'max:999'],
            'assignments.*.preferred_contact_method' => ['nullable', Rule::in(ContactMethod::values())],
            'assignments.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'salutation' => 'Anrede',
            'academic_title' => 'Akademischer Titel',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'gender' => 'Geschlecht',
            'birth_date' => 'Geburtsdatum',
            'preferred_contact_method' => 'Bevorzugte Kontaktart',
            'emails.*.email' => 'E-Mail-Adresse',
            'phones.*.number' => 'Telefonnummer',
            'assignments' => 'Kundenzuordnung',
            'assignments.*.customer_id' => 'Kunde',
            'assignments.*.priority' => 'Priorität',
        ];
    }

    /**
     * Ansprechpartner gibt es ausschliesslich bei Firmenkunden.
     *
     * @return Collection<int, Customer>
     */
    private function companyCustomers(): Collection
    {
        return Customer::query()
            ->where('type', CustomerType::Company)
            ->active()
            ->orderBy('company_name')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'label' => "{$customer->customer_number} · {$customer->displayName()}",
            ]);
    }

    /**
     * @return Collection<int, ContactRole>
     */
    private function roles(): Collection
    {
        return ContactRole::query()->active()->orderBy('sort_order')->orderBy('name')->get();
    }
}
