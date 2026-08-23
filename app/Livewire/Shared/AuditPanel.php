<?php

namespace App\Livewire\Shared;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Aenderungshistorie eines Datensatzes.
 *
 * Eintraege sind unveraenderlich und ueber die Anwendung nicht loeschbar.
 */
class AuditPanel extends Component
{
    use WithPagination;

    public Model $auditable;

    public function mount(Model $auditable): void
    {
        $this->auditable = $auditable;
    }

    public function render(): View
    {
        return view('livewire.shared.audit-panel', [
            'entries' => $this->entries(),
            'labels' => method_exists($this->auditable, 'auditLabels') ? $this->auditable->auditLabels() : [],
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    private function entries(): LengthAwarePaginator
    {
        return $this->auditable
            ->auditLogs()
            ->with('user')
            ->paginate(20);
    }
}
