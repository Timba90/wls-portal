<?php

namespace App\Livewire\Search;

use App\Actions\Search\GlobalSearch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Globale Suche im Kopfbereich des Layouts.
 *
 * Durchsucht Kunden, Ansprechpartner, Katalogartikel und Kundenleistungen.
 * Archivierte Datensätze sind ausgeschlossen.
 */
class GlobalSearchBar extends Component
{
    public string $term = '';

    public bool $showResults = false;

    public function updatedTerm(): void
    {
        $this->showResults = mb_strlen(trim($this->term)) >= 2;
    }

    public function close(): void
    {
        $this->reset('term', 'showResults');
    }

    public function render(GlobalSearch $globalSearch): View
    {
        return view('livewire.search.global-search-bar', [
            'groups' => $this->showResults ? $globalSearch($this->term) : collect(),
        ]);
    }
}
