<?php

namespace App\Livewire\Registrar;

use App\Livewire\Concerns\WithInventoryAssignment;
use App\Models\Domain;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Detailseite einer Domain.
 *
 * Der technische Stand kommt aus der Schnittstelle des Registrars und ist hier
 * nur zu lesen — geaendert wird er beim Anbieter. Von Hand gepflegt wird alles
 * andere: die Zuordnung zu Kunde und Leistung sowie Notizen, Dokumente und
 * eigene Felder (§525, §933).
 */
#[Layout('components.layouts.app')]
class DomainDetail extends Component
{
    /*
     * Dieselbe Zuordnung wie in der Liste, nur fuer genau einen Datensatz.
     */
    use WithInventoryAssignment;

    public Domain $domain;

    #[Url(as: 'bereich', except: 'notizen')]
    public string $tab = 'notizen';

    public function mount(Domain $domain): void
    {
        $this->domain = $domain;
    }

    public function render(): View
    {
        $this->domain->load(['customer', 'customerService']);

        return view('livewire.registrar.domain-detail')->title($this->domain->name);
    }

    /**
     * Oeffnet die Zuordnung fuer genau diese Domain.
     */
    public function editAssignment(): void
    {
        $this->startAssignment($this->domain->getKey());
    }

    /**
     * @return class-string<Domain>
     */
    protected function inventoryModel(): string
    {
        return Domain::class;
    }
}
