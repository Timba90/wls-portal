<?php

namespace App\Livewire\Projects;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\UpdateProject;
use App\Enums\OperationsStatus;
use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Formular zum Anlegen und Bearbeiten eines Projekts.
 *
 * Der Kunde wird nur beim Anlegen gewaehlt: ein Projekt wechselt nicht den
 * Kunden, sonst haengen Positionen aus Kundenleistungen am falschen Vertrag.
 */
#[Layout('components.layouts.app')]
class ProjectForm extends Component
{
    public ?Project $project = null;

    public string $customer_id = '';

    public string $name = '';

    public string $description = '';

    public string $project_type_id = '';

    public string $responsible_user_id = '';

    public string $status = ProjectStatus::Planned->value;

    public string $start_date = '';

    public string $deadline = '';

    public string $risk_note = '';

    public string $backup_status = OperationsStatus::Unknown->value;

    public string $security_status = OperationsStatus::Unknown->value;

    public string $update_status = OperationsStatus::Unknown->value;

    public string $operations_checked_on = '';

    public function mount(?Project $project = null): void
    {
        if (! $project?->exists) {
            // Aus dem Kundendetail heraus ist der Kunde bereits gesetzt.
            $this->customer_id = (string) (request()->integer('kunde') ?: '');

            return;
        }

        abort_if($project->isArchived(), 403, 'Archivierte Projekte können nicht bearbeitet werden.');

        $this->project = $project;
        $this->customer_id = (string) $project->customer_id;
        $this->name = $project->name;
        $this->description = (string) $project->description;
        $this->project_type_id = (string) ($project->project_type_id ?? '');
        $this->responsible_user_id = (string) ($project->responsible_user_id ?? '');
        $this->status = $project->status->value;
        $this->start_date = $project->start_date?->format('Y-m-d') ?? '';
        $this->deadline = $project->deadline?->format('Y-m-d') ?? '';
        $this->risk_note = (string) $project->risk_note;
        $this->backup_status = $project->backup_status->value;
        $this->security_status = $project->security_status->value;
        $this->update_status = $project->update_status->value;
        $this->operations_checked_on = $project->operations_checked_on?->format('Y-m-d') ?? '';
    }

    public function isEditing(): bool
    {
        return $this->project !== null;
    }

    public function save(CreateProject $createProject, UpdateProject $updateProject): void
    {
        $validated = $this->validate();

        $attribute = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'project_type_id' => $validated['project_type_id'] ?: null,
            'responsible_user_id' => $validated['responsible_user_id'] ?: null,
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?: null,
            'deadline' => $validated['deadline'] ?: null,
            'risk_note' => $validated['risk_note'] ?: null,
            'backup_status' => $validated['backup_status'],
            'security_status' => $validated['security_status'],
            'update_status' => $validated['update_status'],
            'operations_checked_on' => $validated['operations_checked_on'] ?: null,
        ];

        $projekt = $this->isEditing()
            ? $updateProject($this->project, $attribute)
            : $createProject(Customer::findOrFail($validated['customer_id']), $attribute);

        session()->flash('status', $this->isEditing() ? 'Projekt gespeichert.' : 'Projekt angelegt.');

        $this->redirectRoute('projects.show', $projekt, navigate: true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            // Beim Bearbeiten steht der Kunde fest und wird nicht mitgesendet.
            'customer_id' => [Rule::requiredIf(! $this->isEditing()), 'nullable', 'integer', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_type_id' => ['nullable', 'integer', 'exists:project_types,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(array_column(ProjectStatus::selectable(), 'value'))],
            'start_date' => ['nullable', 'date'],
            // Eine Deadline vor dem Beginn wäre ein Planungsfehler, kein Ziel.
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'risk_note' => ['nullable', 'string', 'max:2000'],
            'backup_status' => ['required', Rule::enum(OperationsStatus::class)],
            'security_status' => ['required', Rule::enum(OperationsStatus::class)],
            'update_status' => ['required', Rule::enum(OperationsStatus::class)],
            // Eine Pruefung in der Zukunft hat noch niemand durchgefuehrt.
            'operations_checked_on' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'customer_id' => 'Kunde',
            'name' => 'Projektname',
            'description' => 'Beschreibung',
            'project_type_id' => 'Projekttyp',
            'responsible_user_id' => 'Verantwortlich',
            'status' => 'Status',
            'start_date' => 'Beginn',
            'deadline' => 'Deadline',
            'risk_note' => 'Risiko',
            'backup_status' => 'Backup',
            'security_status' => 'Security',
            'update_status' => 'Updates',
            'operations_checked_on' => 'Betrieb geprüft am',
        ];
    }

    public function render(): View
    {
        return view('livewire.projects.project-form', [
            'customers' => $this->customers(),
            'projectTypes' => $this->projectTypes(),
            'responsibleUsers' => $this->responsibleUsers(),
            'statusOptions' => ProjectStatus::options(ProjectStatus::selectable()),
            'operationsOptions' => OperationsStatus::options(),
        ]);
    }

    /**
     * Kunden fuer die Auswahl, mit Nummer im Namen — bei gleichnamigen Firmen
     * ist sonst nicht erkennbar, welche gemeint ist.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function customers(): Collection
    {
        return Customer::query()
            ->active()
            ->orderBy('company_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Customer $kunde): array => [
                'id' => $kunde->getKey(),
                'name' => $kunde->customer_number.' · '.$kunde->displayName(),
            ]);
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
