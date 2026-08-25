<?php

namespace App\Livewire\Projects;

use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\DeleteMilestone;
use App\Actions\Projects\DeletePosition;
use App\Actions\Projects\RestoreProject;
use App\Actions\Projects\SaveMilestone;
use App\Actions\Projects\SavePosition;
use App\Actions\Projects\SyncProjectMembers;
use App\Enums\CustomerServiceStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectPositionKind;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectPosition;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Detailseite eines Projekts: Plan, Positionen, Team und Stammdaten.
 */
#[Layout('components.layouts.app')]
class ProjectDetail extends Component
{
    public Project $project;

    #[Url(as: 'bereich', except: 'plan')]
    public string $tab = 'plan';

    public bool $showMilestoneForm = false;

    public ?int $editingMilestoneId = null;

    public string $milestoneName = '';

    public string $milestoneNote = '';

    public string $milestoneStatus = '';

    public string $milestoneDueDate = '';

    public bool $showPositionForm = false;

    public ?int $editingPositionId = null;

    /** Herkunft der Position: frei, aus dem Katalog oder aus einer Kundenleistung. */
    public string $positionSource = 'free';

    public string $positionProductId = '';

    public string $positionServiceId = '';

    public string $positionName = '';

    public string $positionKind = '';

    public string $positionQuantity = '1';

    public string $positionUnitPrice = '';

    public string $positionStatus = '';

    public string $newMemberUserId = '';

    public string $newMemberRole = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function render(): View
    {
        $this->project->load([
            'customer',
            'projectType',
            'responsibleUser',
            'milestones',
            'positions.product',
            'positions.customerService',
            'members.user',
        ]);

        return view('livewire.projects.project-detail', [
            'milestoneStatusOptions' => MilestoneStatus::options(),
            'positionKindOptions' => ProjectPositionKind::options(),
            'positionStatusOptions' => CustomerServiceStatus::options(CustomerServiceStatus::selectable()),
            'products' => $this->products(),
            'customerServices' => $this->customerServices(),
            'availableUsers' => $this->availableUsers(),
        ]);
    }

    public function isReadOnly(): bool
    {
        return $this->project->isArchived();
    }

    // -- Projekt -----------------------------------------------------------

    public function archive(ArchiveProject $archiveProject): void
    {
        $archiveProject($this->project);

        $this->project->refresh();
        $this->dispatch('projekt-archiviert');
    }

    public function restore(RestoreProject $restoreProject): void
    {
        $restoreProject($this->project);

        $this->project->refresh();
        $this->dispatch('projekt-reaktiviert');
    }

    // -- Meilensteine ------------------------------------------------------

    public function openMilestoneForm(?int $milestoneId = null): void
    {
        $this->resetValidation();

        $meilenstein = $milestoneId ? $this->project->milestones()->findOrFail($milestoneId) : null;

        $this->editingMilestoneId = $meilenstein?->getKey();
        $this->milestoneName = $meilenstein->name ?? '';
        $this->milestoneNote = $meilenstein->note ?? '';
        $this->milestoneStatus = ($meilenstein->status ?? MilestoneStatus::Open)->value;
        $this->milestoneDueDate = $meilenstein?->due_date?->toDateString() ?? '';
        $this->showMilestoneForm = true;
    }

    public function saveMilestone(SaveMilestone $saveMilestone): void
    {
        $this->validate([
            'milestoneName' => ['required', 'string', 'max:255'],
            'milestoneNote' => ['nullable', 'string', 'max:255'],
            'milestoneStatus' => ['required', Rule::in(MilestoneStatus::values())],
            'milestoneDueDate' => ['nullable', 'date'],
        ], attributes: [
            'milestoneName' => 'Meilenstein',
            'milestoneNote' => 'Notiz',
            'milestoneStatus' => 'Status',
            'milestoneDueDate' => 'Termin',
        ]);

        $saveMilestone(
            $this->project,
            [
                'name' => $this->milestoneName,
                'note' => $this->milestoneNote ?: null,
                'status' => $this->milestoneStatus,
                'due_date' => $this->milestoneDueDate ?: null,
            ],
            $this->editingMilestone(),
        );

        $this->showMilestoneForm = false;
        $this->dispatch('meilenstein-gespeichert');
    }

    /**
     * Status eines Meilensteins direkt in der Liste weiterschalten.
     */
    public function setMilestoneStatus(int $milestoneId, string $status, SaveMilestone $saveMilestone): void
    {
        $meilenstein = $this->project->milestones()->findOrFail($milestoneId);

        $saveMilestone($this->project, [
            'name' => $meilenstein->name,
            'note' => $meilenstein->note,
            'status' => MilestoneStatus::from($status)->value,
            'due_date' => $meilenstein->due_date?->toDateString(),
        ], $meilenstein);

        $this->dispatch('meilenstein-gespeichert');
    }

    public function deleteMilestone(int $milestoneId, DeleteMilestone $deleteMilestone): void
    {
        $deleteMilestone($this->project->milestones()->findOrFail($milestoneId));

        $this->dispatch('meilenstein-geloescht');
    }

    // -- Positionen --------------------------------------------------------

    public function openPositionForm(?int $positionId = null): void
    {
        $this->resetValidation();
        $this->reset('positionProductId', 'positionServiceId');

        $position = $positionId ? $this->project->positions()->findOrFail($positionId) : null;

        $this->editingPositionId = $position?->getKey();
        $this->positionSource = match (true) {
            $position?->customer_service_id !== null => 'service',
            $position?->product_id !== null => 'catalog',
            default => 'free',
        };
        $this->positionProductId = (string) ($position->product_id ?? '');
        $this->positionServiceId = (string) ($position->customer_service_id ?? '');
        $this->positionName = $position->name ?? '';
        $this->positionKind = ($position->kind ?? ProjectPositionKind::OneTime)->value;
        $this->positionQuantity = $position ? rtrim(rtrim((string) $position->quantity, '0'), '.') : '1';
        $this->positionUnitPrice = $position ? $position->unitPrice()->toInput() : '';
        $this->positionStatus = ($position->status ?? CustomerServiceStatus::Planned)->value;
        $this->showPositionForm = true;
    }

    /**
     * Ein gewaehlter Katalogartikel fuellt Name, Preis und Art als Vorschlag.
     */
    public function updatedPositionProductId(string $value): void
    {
        if ($value === '') {
            return;
        }

        $this->applySuggestion(
            app(SavePosition::class)->suggestionFromProduct(Product::findOrFail($value))
        );
    }

    public function updatedPositionServiceId(string $value): void
    {
        if ($value === '') {
            return;
        }

        $service = $this->project->customer->services()->findOrFail($value);

        $this->applySuggestion(app(SavePosition::class)->suggestionFromService($service));
    }

    public function updatedPositionSource(): void
    {
        $this->reset('positionProductId', 'positionServiceId');
    }

    public function savePosition(SavePosition $savePosition): void
    {
        $this->validate([
            'positionSource' => ['required', Rule::in(['free', 'catalog', 'service'])],
            'positionProductId' => [Rule::requiredIf($this->positionSource === 'catalog'), 'nullable', 'integer'],
            'positionServiceId' => [Rule::requiredIf($this->positionSource === 'service'), 'nullable', 'integer'],
            'positionName' => ['required', 'string', 'max:255'],
            'positionKind' => ['required', Rule::in(ProjectPositionKind::values())],
            'positionQuantity' => ['required', 'numeric', 'min:0'],
            'positionUnitPrice' => ['required', 'string'],
            'positionStatus' => ['required', Rule::in(CustomerServiceStatus::values())],
        ], attributes: [
            'positionProductId' => 'Katalogartikel',
            'positionServiceId' => 'Kundenleistung',
            'positionName' => 'Bezeichnung',
            'positionKind' => 'Art',
            'positionQuantity' => 'Menge',
            'positionUnitPrice' => 'Einzelpreis',
            'positionStatus' => 'Status',
        ]);

        try {
            Money::fromEuroInput($this->positionUnitPrice);
        } catch (\InvalidArgumentException) {
            $this->addError('positionUnitPrice', 'Der Wert ist kein gültiger Geldbetrag.');

            return;
        }

        $savePosition(
            $this->project,
            [
                'product_id' => $this->positionSource === 'catalog' ? (int) $this->positionProductId : null,
                'customer_service_id' => $this->positionSource === 'service' ? (int) $this->positionServiceId : null,
                'name' => $this->positionName,
                'kind' => $this->positionKind,
                'quantity' => $this->positionQuantity,
                'unit_price' => $this->positionUnitPrice,
                'status' => $this->positionStatus,
            ],
            $this->editingPosition(),
        );

        $this->showPositionForm = false;
        $this->dispatch('position-gespeichert');
    }

    public function deletePosition(int $positionId, DeletePosition $deletePosition): void
    {
        $deletePosition($this->project->positions()->findOrFail($positionId));

        $this->dispatch('position-geloescht');
    }

    // -- Team --------------------------------------------------------------

    public function addMember(SyncProjectMembers $syncProjectMembers): void
    {
        $this->validate([
            'newMemberUserId' => ['required', 'integer', 'exists:users,id'],
            'newMemberRole' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'newMemberUserId' => 'Person',
            'newMemberRole' => 'Rolle',
        ]);

        $syncProjectMembers($this->project, [
            ...$this->currentMemberInput(),
            ['user_id' => (int) $this->newMemberUserId, 'role' => $this->newMemberRole ?: null],
        ]);

        $this->reset('newMemberUserId', 'newMemberRole');
        $this->dispatch('team-gespeichert');
    }

    public function removeMember(int $memberId, SyncProjectMembers $syncProjectMembers): void
    {
        $syncProjectMembers(
            $this->project,
            array_values(array_filter(
                $this->currentMemberInput(),
                fn (array $mitglied): bool => $mitglied['id'] !== $memberId,
            )),
        );

        $this->dispatch('team-entfernt');
    }

    /**
     * @return array<int, array{id: int, user_id: int, role: ?string}>
     */
    private function currentMemberInput(): array
    {
        return $this->project->members()->orderBy('sort_order')->get()
            ->map(fn (ProjectMember $mitglied): array => [
                'id' => $mitglied->getKey(),
                'user_id' => $mitglied->user_id,
                'role' => $mitglied->role,
            ])
            ->all();
    }

    /**
     * @param  array{name: string, unit_price: string, kind: string}  $vorschlag
     */
    private function applySuggestion(array $vorschlag): void
    {
        $this->positionName = $vorschlag['name'];
        $this->positionUnitPrice = $vorschlag['unit_price'];
        $this->positionKind = $vorschlag['kind'];
    }

    private function editingMilestone(): ?ProjectMilestone
    {
        return $this->editingMilestoneId
            ? $this->project->milestones()->findOrFail($this->editingMilestoneId)
            : null;
    }

    private function editingPosition(): ?ProjectPosition
    {
        return $this->editingPositionId
            ? $this->project->positions()->findOrFail($this->editingPositionId)
            : null;
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return Product::query()->active()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Nur Leistungen dieses Kunden — eine Projektposition greift nicht auf
     * Vertraege anderer Kunden zu.
     *
     * @return Collection<int, CustomerService>
     */
    private function customerServices(): Collection
    {
        return $this->project->customer->services()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, User>
     */
    private function availableUsers(): Collection
    {
        return User::query()
            ->whereNotIn('id', $this->project->members->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
