<?php

namespace App\Livewire\Archive;

use App\Enums\CatalogStatus;
use App\Enums\CustomerServiceStatus;
use App\Enums\CustomerStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Archivansichten.
 *
 * Archivierte Datensätze erscheinen bewusst nicht in der globalen Suche —
 * hier lassen sie sich gezielt wiederfinden.
 */
#[Layout('components.layouts.app')]
#[Title('Archiv')]
class ArchivePage extends Component
{
    use WithPagination;

    #[Url(as: 'bereich', except: 'kunden')]
    public string $tab = 'kunden';

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.archive.archive-page', [
            'records' => $this->records(),
            'counts' => $this->counts(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'kunden' => Customer::query()->archived()->count(),
            'ansprechpartner' => Contact::query()->whereNotNull('archived_at')->count(),
            'artikel' => Product::query()->where('status', CatalogStatus::Archived)->count(),
            'leistungen' => CustomerService::query()->where('status', CustomerServiceStatus::Archived)->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    private function records(): LengthAwarePaginator
    {
        $term = '%'.$this->search.'%';

        return match ($this->tab) {
            'ansprechpartner' => Contact::query()
                ->whereNotNull('archived_at')
                ->with(['assignments.customer'])
                ->when($this->search !== '', fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)))
                ->orderByDesc('archived_at')
                ->paginate(25),

            'artikel' => Product::query()
                ->where('status', CatalogStatus::Archived)
                ->when($this->search !== '', fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'like', $term)
                        ->orWhere('internal_name', 'like', $term)))
                ->orderByDesc('archived_at')
                ->paginate(25),

            'leistungen' => CustomerService::query()
                ->where('status', CustomerServiceStatus::Archived)
                ->with('customer')
                ->when($this->search !== '', fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $customers) => $customers
                            ->where('company_name', 'like', $term)
                            ->orWhere('short_label', 'like', $term)
                            ->orWhere('customer_number', 'like', $term))))
                ->orderByDesc('archived_at')
                ->paginate(25),

            default => Customer::query()
                ->where('status', CustomerStatus::Archived)
                ->when($this->search !== '', fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('customer_number', 'like', $term)
                        ->orWhere('company_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('short_label', 'like', $term)))
                ->orderByDesc('archived_at')
                ->paginate(25),
        };
    }
}
