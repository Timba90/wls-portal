<?php

namespace App\Livewire\Registrar;

use App\Actions\Registrar\CreateManualDomain;
use App\Actions\Registrar\UpdateManualDomain;
use App\Models\Domain;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formular fuer eine von Hand gepflegte Domain.
 *
 * Nur fuer diese: der technische Stand einer importierten Domain kommt aus der
 * Schnittstelle ihres Anbieters und wird dort geaendert. Wer die Bearbeitung
 * einer importierten Domain aufruft, bekommt deshalb 403 und kein Formular, das
 * beim naechsten Abgleich stillschweigend zurueckspringt.
 */
#[Layout('components.layouts.app')]
class DomainForm extends Component
{
    public ?Domain $domain = null;

    public string $name = '';

    public string $status = 'aktiv';

    public string $registered_on = '';

    public string $expires_on = '';

    public bool $auto_renew = false;

    /**
     * Nameserver als Text, einer je Zeile — so tippt man sie ab.
     */
    public string $nameservers = '';

    public function mount(?Domain $domain = null): void
    {
        if (! $domain?->exists) {
            return;
        }

        abort_if(
            ! $domain->isMaintainedByHand(),
            403,
            'Der technische Stand importierter Domains wird beim Anbieter geändert, nicht hier.',
        );

        $this->domain = $domain;
        $this->name = $domain->name;
        $this->status = $domain->status;
        $this->registered_on = $domain->registered_on?->format('Y-m-d') ?? '';
        $this->expires_on = $domain->expires_on?->format('Y-m-d') ?? '';
        $this->auto_renew = $domain->auto_renew;
        $this->nameservers = implode("\n", $domain->nameservers ?? []);
    }

    public function isEditing(): bool
    {
        return $this->domain !== null;
    }

    public function render(): View
    {
        return view('livewire.registrar.domain-form');
    }

    public function save(CreateManualDomain $anlegen, UpdateManualDomain $aendern): void
    {
        // Vor der Pruefung, damit die Eindeutigkeit gegen die Schreibweise
        // prueft, in der der Name auch gespeichert wird.
        $this->name = Domain::normalizeName($this->name);

        $validated = $this->validate($this->rules(), attributes: $this->validationAttributes());

        $attribute = [
            'name' => $validated['name'],
            'status' => $validated['status'],
            'registered_on' => $validated['registered_on'] ?: null,
            'expires_on' => $validated['expires_on'] ?: null,
            'auto_renew' => $this->auto_renew,
            'nameservers' => $this->nameserverList(),
        ];

        $domain = $this->isEditing()
            ? $aendern($this->domain, $attribute)
            : $anlegen($attribute);

        session()->flash('status', $this->isEditing() ? 'Domain gespeichert.' : 'Domain angelegt.');

        $this->redirectRoute('domains.show', $domain, navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $ablauf = ['nullable', 'date'];

        // Der Vergleich nur, wenn es etwas zu vergleichen gibt: gegen ein
        // leeres Feld zu pruefen ergaebe eine Meldung ueber ein Datum, das
        // niemand eingegeben hat.
        if ($this->registered_on !== '') {
            $ablauf[] = 'after_or_equal:registered_on';
        }

        return [
            'name' => [
                'required',
                'string',
                'max:253',
                // Mindestens ein Punkt und eine Endung aus Buchstaben:
                // „beispiel" ist keine Domain, „beispiel.de" schon.
                'regex:/^[\p{L}\p{N}][\p{L}\p{N}.\-]*\.[\p{L}]{2,}$/u',
                Rule::unique('domains', 'name')->ignore($this->domain?->getKey()),
            ],
            'status' => ['required', 'string', 'max:40'],
            'registered_on' => ['nullable', 'date'],
            'expires_on' => $ablauf,
            'auto_renew' => ['boolean'],
            'nameservers' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'Domainname',
            'status' => 'Status',
            'registered_on' => 'Registriert am',
            'expires_on' => 'Läuft ab am',
            'auto_renew' => 'Verlängert sich automatisch',
            'nameservers' => 'Nameserver',
        ];
    }

    /**
     * Die Nameserver aus dem Textfeld.
     *
     * Getrennt wird an Zeilenumbruechen und Kommata — beides kommt beim
     * Abtippen vor. Leere Zeilen und Doppelte fallen weg.
     *
     * @return array<int, string>
     */
    private function nameserverList(): array
    {
        $zeilen = preg_split('/[\r\n,]+/', $this->nameservers) ?: [];

        $namen = [];

        foreach ($zeilen as $zeile) {
            $name = Domain::normalizeName($zeile);

            if ($name !== '') {
                $namen[] = $name;
            }
        }

        return array_values(array_unique($namen));
    }
}
