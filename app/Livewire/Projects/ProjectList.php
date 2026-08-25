<?php

namespace App\Livewire\Projects;

use App\Actions\Projects\CalculateProjectMetrics;
use App\Enums\ProjectStatus;
use App\Livewire\Concerns\WithConfigurableTable;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use App\Support\StatusTally;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Projektliste mit Kennzahlen, Statusfiltern und den naechsten Terminen.
 */
#[Layout('components.layouts.app')]
#[Title('Projekte')]
class ProjectList extends Component
{
    use WithConfigurableTable, WithPagination;

    /**
     * Sammelfilter fuer alle laufenden Status. Die Voreinstellung der Liste —
     * abgeschlossene und archivierte Projekte stehen sonst dauerhaft im Weg.
     */
    public const OPEN_FILTER = 'open';

    #[Url(as: 'suche', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: self::OPEN_FILTER)]
    public string $status = self::OPEN_FILTER;

    #[Url(as: 'typ', except: '')]
    public string $projectTypeId = '';

    #[Url(as: 'verantwortlich', except: '')]
    public string $responsibleUserId = '';

    /** @var array{column: string, direction: string} */
    public array $sort = ['column' => 'deadline', 'direction' => 'asc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProjectTypeId(): void
    {
        $this->resetPage();
    }

    public function updatedResponsibleUserId(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'projectTypeId', 'responsibleUserId');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.projects.project-list', [
            'projects' => $this->projects(),
            'metrics' => app(CalculateProjectMetrics::class)(),
            'projectTypes' => $this->projectTypes(),
            'responsibleUsers' => $this->responsibleUsers(),
        ]);
    }

    protected function tableKey(): string
    {
        return 'projects';
    }

    /**
     * @return array<string, array{label: string, sortable?: bool, width?: int|null, fixed?: bool, default_visible?: bool}>
     */
    protected function columnDefinitions(): array
    {
        return [
            'project' => ['label' => 'Projekt', 'sortable' => false, 'fixed' => true],
            'volume' => ['label' => 'Umsatz', 'sortable' => false],
            'recurring_volume' => ['label' => 'Umsatz / Mon.', 'sortable' => false],
            'backup_status' => ['label' => 'Backup'],
            'security_status' => ['label' => 'Security'],
            'update_status' => ['label' => 'Updates'],
            'status' => ['label' => 'Status'],
            'customer' => ['label' => 'Kunde', 'sortable' => false, 'default_visible' => false],
            'project_number' => ['label' => 'Projektnummer', 'default_visible' => false],
            'type' => ['label' => 'Typ', 'sortable' => false, 'default_visible' => false],
            'responsible' => ['label' => 'Verantwortlich', 'sortable' => false, 'default_visible' => false],
            'start_date' => ['label' => 'Beginn', 'default_visible' => false],
            'deadline' => ['label' => 'Deadline', 'default_visible' => false],
            'progress' => ['label' => 'Fortschritt', 'sortable' => false, 'default_visible' => false],
            'operations_checked_on' => ['label' => 'Betrieb geprüft', 'default_visible' => false],
            'milestones' => ['label' => 'Meilensteine', 'sortable' => false, 'default_visible' => false],
        ];
    }

    /**
     * Rasteranteil und Ausrichtung je Spalte, entsprechend dem Entwurf.
     *
     * @return array<string, array{breite: string, rechts?: bool}>
     */
    public function columnLayout(): array
    {
        return [
            'project' => ['breite' => '2.2fr'],
            'volume' => ['breite' => '1fr', 'rechts' => true],
            'recurring_volume' => ['breite' => '1fr', 'rechts' => true],
            'backup_status' => ['breite' => '0.85fr'],
            'security_status' => ['breite' => '0.85fr'],
            'update_status' => ['breite' => '0.85fr'],
            'status' => ['breite' => '0.9fr'],
            'customer' => ['breite' => '1.2fr'],
            'project_number' => ['breite' => '0.9fr'],
            'type' => ['breite' => '0.9fr'],
            'responsible' => ['breite' => '1fr'],
            'start_date' => ['breite' => '0.8fr'],
            'deadline' => ['breite' => '1fr'],
            'progress' => ['breite' => '1.1fr'],
            'operations_checked_on' => ['breite' => '0.95fr'],
            'milestones' => ['breite' => '0.8fr', 'rechts' => true],
        ];
    }

    /**
     * Statusfilter mit Zaehlern. Suche, Typ und Verantwortlicher wirken mit,
     * damit die Zahlen zu dem passen, was der Wechsel tatsaechlich zeigt.
     *
     * @return array<int, array{wert: string, label: string, anzahl: int}>
     */
    public function statusFilters(): array
    {
        $basis = fn (): Builder => Project::query()
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($this->projectTypeId !== '', fn (Builder $query) => $query->where('project_type_id', $this->projectTypeId))
            ->when($this->responsibleUserId !== '', fn (Builder $query) => $query->where('responsible_user_id', $this->responsibleUserId));

        // Eine gruppierte Abfrage statt einer je Schaltflaeche.
        $gezaehlt = StatusTally::from($basis());

        $filter = [
            ['wert' => '', 'label' => 'Alle', 'anzahl' => $gezaehlt->total()],
            ['wert' => self::OPEN_FILTER, 'label' => 'Offen', 'anzahl' => $gezaehlt->of(...Project::openStatusValues())],
        ];

        foreach (ProjectStatus::cases() as $status) {
            $filter[] = [
                'wert' => $status->value,
                'label' => $status->label(),
                'anzahl' => $gezaehlt->of($status->value),
            ];
        }

        return $filter;
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    private function projects(): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['customer', 'projectType', 'responsibleUser', 'milestones', 'positions'])
            ->when($this->search !== '', fn (Builder $q) => $this->applySearch($q))
            ->when($this->status === self::OPEN_FILTER, fn (Builder $q) => $q->open())
            ->when(
                $this->status !== '' && $this->status !== self::OPEN_FILTER,
                fn (Builder $q) => $q->where('status', $this->status),
            )
            ->when($this->projectTypeId !== '', fn (Builder $q) => $q->where('project_type_id', $this->projectTypeId))
            ->when($this->responsibleUserId !== '', fn (Builder $q) => $q->where('responsible_user_id', $this->responsibleUserId));

        $this->applySorting($query);

        return $query->paginate(25);
    }

    /**
     * @param  Builder<Project>  $query
     */
    private function applySearch(Builder $query): void
    {
        $term = '%'.$this->search.'%';

        $query->where(function (Builder $query) use ($term): void {
            $query->where('project_number', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhereHas('customer', function (Builder $query) use ($term): void {
                    $query->where('company_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('customer_number', 'like', $term);
                });
        });
    }

    /**
     * @param  Builder<Project>  $query
     */
    private function applySorting(Builder $query): void
    {
        $richtung = $this->sort['direction'] === 'desc' ? 'desc' : 'asc';
        $spalte = $this->sortColumn();

        // Projekte ohne Deadline gehoeren ans Ende, nicht an den Anfang —
        // sonst stuende das Unbefristete ueber dem Dringenden.
        if ($spalte === 'deadline') {
            $query->orderByRaw('deadline is null')->orderBy('deadline', $richtung);

            return;
        }

        $query->orderBy($spalte, $richtung);
    }

    /**
     * Nur bekannte Spalten zulassen — die Sortierung kommt aus der URL.
     */
    private function sortColumn(): string
    {
        $sortable = ['project_number', 'name', 'status', 'deadline', 'start_date', 'created_at', 'updated_at'];

        return in_array($this->sort['column'], $sortable, strict: true)
            ? $this->sort['column']
            : 'deadline';
    }

    /**
     * @return Collection<int, ProjectType>
     */
    private function projectTypes(): Collection
    {
        return ProjectType::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, User>
     */
    private function responsibleUsers(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
