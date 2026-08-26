<?php

namespace App\Livewire\System;

use App\Enums\RegistrarProvider;
use App\Models\IntegrationCredential;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Zugangsdaten der Schnittstellen pflegen.
 *
 * Die Felder sind einseitig: was hinterlegt ist, wird nie zurueckgelesen.
 * Angezeigt wird nur, ob etwas hinterlegt ist — ein Kennwort, das die Seite
 * wieder ausliefert, steht im Klartext im Quelltext des Browsers und in jedem
 * Zwischenspeicher davor.
 */
#[Layout('components.layouts.app')]
#[Title('Schnittstellen')]
class IntegrationSettings extends Component
{
    /**
     * Eingaben je Anbieter; leer, solange nichts getippt wurde.
     *
     * @var array<string, array<string, string>>
     */
    public array $input = [];

    public function mount(): void
    {
        foreach (RegistrarProvider::cases() as $anbieter) {
            $this->input[$anbieter->value] = array_fill_keys(
                array_keys($this->fieldsFor($anbieter)),
                '',
            );
        }
    }

    public function render(): View
    {
        return view('livewire.system.integration-settings', [
            'providers' => RegistrarProvider::cases(),
        ]);
    }

    /**
     * Welche Felder ein Anbieter braucht, mit Beschriftung und Hinweis.
     *
     * @return array<string, array{label: string, hint?: string, optional?: bool}>
     */
    public function fieldsFor(RegistrarProvider $provider): array
    {
        return match ($provider) {
            RegistrarProvider::Inwx => [
                'username' => ['label' => 'Benutzername'],
                'password' => ['label' => 'Kennwort'],
                'shared_secret' => [
                    'label' => 'Geheimnis der Zwei-Faktor-Anmeldung',
                    'hint' => 'Nur nötig, wenn das Konto eine Zwei-Faktor-Anmeldung verlangt.',
                    'optional' => true,
                ],
            ],
            RegistrarProvider::DomainReselling => [
                'username' => ['label' => 'Benutzername'],
                'password' => ['label' => 'Kennwort'],
            ],
        };
    }

    /**
     * Welche Felder eines Anbieters hinterlegt sind — ohne die Werte selbst.
     *
     * @return array<string, bool>
     */
    public function storedFields(RegistrarProvider $provider): array
    {
        $werte = IntegrationCredential::valuesFor($provider);

        return array_map(
            fn (string $feld): bool => filled($werte[$feld] ?? null),
            array_combine(array_keys($this->fieldsFor($provider)), array_keys($this->fieldsFor($provider))),
        );
    }

    public function lastChange(RegistrarProvider $provider): ?IntegrationCredential
    {
        return IntegrationCredential::query()
            ->with('updatedBy:id,name')
            ->where('provider', $provider->value)
            ->first();
    }

    /**
     * Speichert die ausgefuellten Felder eines Anbieters.
     *
     * Leere Felder lassen den bisherigen Wert stehen: wer nur das Kennwort
     * wechselt, soll den Benutzernamen nicht erneut eintippen muessen.
     */
    public function save(string $provider): void
    {
        $anbieter = RegistrarProvider::from($provider);
        $felder = $this->fieldsFor($anbieter);

        $eingaben = array_filter(
            array_intersect_key($this->input[$provider] ?? [], $felder),
            fn (mixed $wert): bool => is_string($wert) && trim($wert) !== '',
        );

        if ($eingaben === []) {
            $this->dispatch('zugang-unveraendert');

            return;
        }

        $eintrag = IntegrationCredential::query()->firstOrNew(['provider' => $anbieter->value]);

        $eintrag->fill([
            'credentials' => array_merge($eintrag->credentials ?? [], array_map('trim', $eingaben)),
            'updated_by' => auth()->id(),
        ]);

        $eintrag->save();

        $this->mount();
        $this->dispatch('zugang-gespeichert');
    }

    /**
     * Entfernt die Zugangsdaten eines Anbieters vollstaendig.
     */
    public function forget(string $provider): void
    {
        IntegrationCredential::query()->where('provider', $provider)->delete();

        $this->mount();
        $this->dispatch('zugang-entfernt');
    }
}
