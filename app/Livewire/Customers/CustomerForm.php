<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Enums\ContactChannelType;
use App\Enums\CustomerType;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formular zum Anlegen und Bearbeiten eines Kunden.
 *
 * Eigene Seite statt Modal, weil Privatkunden beliebig viele E-Mail-Adressen
 * und Telefonnummern besitzen koennen.
 */
#[Layout('components.layouts.app')]
class CustomerForm extends Component
{
    public ?Customer $customer = null;

    public string $type = CustomerType::Company->value;

    public string $company_name = '';

    public string $salutation = '';

    public string $academic_title = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $short_label = '';

    public string $internal_code = '';

    public string $responsible_user_id = '';

    /** @var array<int, array{email: string, type: string, is_primary: bool}> */
    public array $emails = [];

    /** @var array<int, array{number: string, type: string, is_primary: bool}> */
    public array $phones = [];

    public function mount(?Customer $customer = null): void
    {
        if (! $customer?->exists) {
            $this->addEmail();
            $this->addPhone();

            return;
        }

        $this->customer = $customer;
        $this->type = $customer->type->value;
        $this->company_name = (string) $customer->company_name;
        $this->salutation = $customer->salutation?->value ?? '';
        $this->academic_title = (string) $customer->academic_title;
        $this->first_name = (string) $customer->first_name;
        $this->last_name = (string) $customer->last_name;
        $this->birth_date = $customer->birth_date?->format('Y-m-d') ?? '';
        $this->gender = $customer->gender?->value ?? '';
        $this->short_label = $customer->short_label;
        $this->internal_code = $customer->internal_code;
        $this->responsible_user_id = (string) ($customer->responsible_user_id ?? '');

        $this->emails = $customer->emailAddresses
            ->map(fn ($email): array => [
                'email' => $email->email,
                'type' => $email->type->value,
                'is_primary' => $email->is_primary,
            ])
            ->all();

        $this->phones = $customer->phoneNumbers
            ->map(fn ($phone): array => [
                'number' => $phone->number,
                'type' => $phone->type->value,
                'is_primary' => $phone->is_primary,
            ])
            ->all();

        if ($this->isPrivate()) {
            $this->emails === [] && $this->addEmail();
            $this->phones === [] && $this->addPhone();
        }
    }

    public function isPrivate(): bool
    {
        return $this->type === CustomerType::Private->value;
    }

    public function isEditing(): bool
    {
        return $this->customer?->exists ?? false;
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

    public function save(CreateCustomer $createCustomer, UpdateCustomer $updateCustomer): void
    {
        $validated = $this->validate($this->rules(), attributes: $this->validationAttributes());

        $payload = [
            ...$validated,
            'responsible_user_id' => $this->responsible_user_id !== '' ? (int) $this->responsible_user_id : null,
            'emails' => $this->isPrivate() ? $this->emails : [],
            'phones' => $this->isPrivate() ? $this->phones : [],
        ];

        $customer = $this->isEditing()
            ? $updateCustomer($this->customer, $payload)
            : $createCustomer($payload);

        session()->flash('erfolg', $this->isEditing()
            ? 'Kunde gespeichert.'
            : "Kunde {$customer->customer_number} angelegt.");

        $this->redirectRoute('customers.show', $customer, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.customers.customer-form', [
            'typeOptions' => CustomerType::options(),
            'salutationOptions' => Salutation::options(),
            'genderOptions' => Gender::options(),
            'emailTypeOptions' => ContactChannelType::options(ContactChannelType::forEmail()),
            'phoneTypeOptions' => ContactChannelType::options(ContactChannelType::forPhone()),
            'responsibleUsers' => $this->responsibleUsers(),
        ]);
    }

    /**
     * Typabhaengige Pflichtfelder: die Datenbank kann sie beim gemeinsamen
     * Kundenschema nicht erzwingen, deshalb geschieht das hier.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        $isCompany = ! $this->isPrivate();

        return [
            'type' => ['required', Rule::in(CustomerType::values())],
            'company_name' => [Rule::requiredIf($isCompany), 'nullable', 'string', 'max:255'],
            'salutation' => ['nullable', Rule::in(Salutation::values())],
            'academic_title' => ['nullable', 'string', 'max:60'],
            'first_name' => [Rule::requiredIf(! $isCompany), 'nullable', 'string', 'max:120'],
            'last_name' => [Rule::requiredIf(! $isCompany), 'nullable', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(Gender::values())],
            'short_label' => ['required', 'string', 'max:255'],
            'internal_code' => ['required', 'string', 'max:32'],
            'emails.*.email' => ['nullable', 'email', 'max:255'],
            'emails.*.type' => ['required', Rule::in(ContactChannelType::values())],
            'phones.*.number' => ['nullable', 'string', 'max:60'],
            'phones.*.type' => ['required', Rule::in(ContactChannelType::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'type' => 'Kundentyp',
            'company_name' => 'Firmenname',
            'salutation' => 'Anrede',
            'academic_title' => 'Akademischer Titel',
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'birth_date' => 'Geburtsdatum',
            'gender' => 'Geschlecht',
            'short_label' => 'Kurzbezeichnung',
            'internal_code' => 'Internes Kürzel',
            'emails.*.email' => 'E-Mail-Adresse',
            'phones.*.number' => 'Telefonnummer',
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function responsibleUsers(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
