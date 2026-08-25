<?php

use App\Enums\CustomerServiceStatus;
use App\Enums\MilestoneStatus;
use App\Enums\OperationsStatus;
use App\Enums\ProjectPositionKind;
use App\Enums\ProjectStatus;
use App\Livewire\Projects\ProjectDetail;
use App\Livewire\Projects\ProjectForm;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Projects\ProjectTypeList;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPosition;
use App\Models\ProjectType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
});

it('zeigt in der Liste standardmaessig nur offene Projekte', function (): void {
    Project::factory()->create(['name' => 'Laufender Relaunch', 'status' => ProjectStatus::Active]);
    Project::factory()->completed()->create(['name' => 'Fertiger Shop']);

    Livewire::actingAs($this->benutzer)
        ->test(ProjectList::class)
        ->assertSee('Laufender Relaunch')
        ->assertDontSee('Fertiger Shop');
});

it('zeigt abgeschlossene Projekte auf Wunsch', function (): void {
    Project::factory()->completed()->create(['name' => 'Fertiger Shop']);

    Livewire::actingAs($this->benutzer)
        ->test(ProjectList::class)
        ->call('setStatus', ProjectStatus::Completed->value)
        ->assertSee('Fertiger Shop');
});

it('durchsucht Projektnummer, Name und Kunde', function (string $suchbegriff): void {
    $kunde = Customer::factory()->create(['company_name' => 'Hansen Logistik GmbH']);

    Project::factory()->for($kunde)->create(['name' => 'Portal Hansen']);
    Project::factory()->create(['name' => 'Ganz anderes Projekt']);

    Livewire::actingAs($this->benutzer)
        ->test(ProjectList::class)
        ->set('search', $suchbegriff)
        ->assertSee('Portal Hansen')
        ->assertDontSee('Ganz anderes Projekt');
})->with(['Portal', 'Hansen']);

it('sortiert Projekte ohne Deadline ans Ende', function (): void {
    Project::factory()->create(['name' => 'Ohne Termin', 'deadline' => null]);
    Project::factory()->create(['name' => 'Mit Termin', 'deadline' => now()->addDays(5)]);

    $projekte = Livewire::actingAs($this->benutzer)
        ->test(ProjectList::class)
        ->viewData('projects');

    expect($projekte->pluck('name')->all())->toBe(['Mit Termin', 'Ohne Termin']);
});

it('zeigt die Betriebsampeln in der Uebersicht', function (): void {
    Project::factory()->create([
        'name' => 'Portalrelaunch',
        'status' => ProjectStatus::Active,
        'backup_status' => OperationsStatus::Ok,
        'security_status' => OperationsStatus::Critical,
        'update_status' => OperationsStatus::Attention,
    ]);

    Livewire::actingAs($this->benutzer)
        ->test(ProjectList::class)
        ->assertSee('Portalrelaunch')
        ->assertSee('Backup')
        ->assertSee('Security')
        ->assertSee('Updates')
        ->assertSee('Kritisch');
});

it('zeigt in der Uebersicht weder Termine noch Fortschritt', function (): void {
    $projekt = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectMilestone::factory()->for($projekt)->create([
        'name' => 'Design abgenommen',
        'due_date' => now()->addDays(4),
    ]);

    // Deadline, Fortschritt und Termine hat der Auftraggeber aus der
    // Uebersicht genommen — sie stehen im Projekt selbst.
    Livewire::actingAs($this->benutzer)
        ->test(ProjectList::class)
        ->assertDontSee('Design abgenommen')
        ->assertDontSee('Nächste Termine');
});

it('nimmt die Betriebsampeln ueber das Formular entgegen', function (): void {
    $projekt = Project::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(ProjectForm::class, ['project' => $projekt])
        ->set('backup_status', OperationsStatus::Ok->value)
        ->set('security_status', OperationsStatus::Attention->value)
        ->set('update_status', OperationsStatus::Critical->value)
        ->set('operations_checked_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect($projekt->fresh())
        ->backup_status->toBe(OperationsStatus::Ok)
        ->security_status->toBe(OperationsStatus::Attention)
        ->update_status->toBe(OperationsStatus::Critical)
        ->and($projekt->fresh()->operations_checked_on->isToday())->toBeTrue();
});

it('weist eine Betriebspruefung in der Zukunft zurueck', function (): void {
    $projekt = Project::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(ProjectForm::class, ['project' => $projekt])
        ->set('operations_checked_on', now()->addDay()->toDateString())
        ->call('save')
        ->assertHasErrors(['operations_checked_on']);
});

it('legt ueber das Formular ein Projekt mit Nummer an', function (): void {
    $kunde = Customer::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(ProjectForm::class)
        ->set('customer_id', (string) $kunde->id)
        ->set('name', 'Neues Portal')
        ->set('status', ProjectStatus::Planned->value)
        ->call('save')
        ->assertHasNoErrors();

    $projekt = Project::query()->firstOrFail();

    expect($projekt->name)->toBe('Neues Portal')
        ->and($projekt->project_number)->toBe('PR-00001')
        ->and($projekt->customer_id)->toBe($kunde->id);
});

it('weist eine Deadline vor dem Beginn zurueck', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(ProjectForm::class)
        ->set('customer_id', (string) Customer::factory()->create()->id)
        ->set('name', 'Rückwärtsprojekt')
        ->set('start_date', now()->addDays(20)->toDateString())
        ->set('deadline', now()->addDays(5)->toDateString())
        ->call('save')
        ->assertHasErrors(['deadline']);
});

it('laesst den Status Archiviert nicht von Hand setzen', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(ProjectForm::class)
        ->set('customer_id', (string) Customer::factory()->create()->id)
        ->set('name', 'Schleichweg')
        ->set('status', ProjectStatus::Archived->value)
        ->call('save')
        ->assertHasErrors(['status']);
});

it('oeffnet archivierte Projekte nicht zum Bearbeiten', function (): void {
    $projekt = Project::factory()->archived()->create();

    $this->actingAs($this->benutzer)
        ->get(route('projects.edit', $projekt))
        ->assertForbidden();
});

it('legt einen Meilenstein an und schaltet ihn auf erledigt', function (): void {
    $projekt = Project::factory()->create();

    $komponente = Livewire::actingAs($this->benutzer)
        ->test(ProjectDetail::class, ['project' => $projekt])
        ->call('openMilestoneForm')
        ->set('milestoneName', 'Konzept abgenommen')
        ->set('milestoneStatus', MilestoneStatus::Open->value)
        ->call('saveMilestone')
        ->assertHasNoErrors();

    $meilenstein = $projekt->milestones()->firstOrFail();

    expect($meilenstein->name)->toBe('Konzept abgenommen');

    $komponente->call('setMilestoneStatus', $meilenstein->id, MilestoneStatus::Done->value);

    expect($meilenstein->refresh()->status)->toBe(MilestoneStatus::Done)
        ->and($projekt->load('milestones')->progressPercentage())->toBe(100);
});

it('uebernimmt Name und Preis eines Katalogartikels als Vorschlag', function (): void {
    $projekt = Project::factory()->create();
    $artikel = Product::factory()->create(['name' => 'Hosting Business']);

    Livewire::actingAs($this->benutzer)
        ->test(ProjectDetail::class, ['project' => $projekt])
        ->call('openPositionForm')
        ->set('positionSource', 'catalog')
        ->set('positionProductId', (string) $artikel->id)
        ->assertSet('positionName', 'Hosting Business')
        ->set('positionQuantity', '2')
        ->set('positionUnitPrice', '150,00')
        ->set('positionKind', ProjectPositionKind::OneTime->value)
        ->set('positionStatus', CustomerServiceStatus::Active->value)
        ->call('savePosition')
        ->assertHasNoErrors();

    $position = $projekt->positions()->firstOrFail();

    expect($position->product_id)->toBe($artikel->id)
        ->and($position->total()->cents)->toBe(30000)
        ->and($projekt->load('positions')->oneTimeVolume()->cents)->toBe(30000);
});

it('weist einen unlesbaren Einzelpreis zurueck', function (): void {
    $projekt = Project::factory()->create();

    Livewire::actingAs($this->benutzer)
        ->test(ProjectDetail::class, ['project' => $projekt])
        ->call('openPositionForm')
        ->set('positionName', 'Krumme Position')
        ->set('positionQuantity', '1')
        ->set('positionUnitPrice', 'sehr teuer')
        ->set('positionKind', ProjectPositionKind::OneTime->value)
        ->set('positionStatus', CustomerServiceStatus::Active->value)
        ->call('savePosition')
        ->assertHasErrors(['positionUnitPrice']);

    expect($projekt->positions()->count())->toBe(0);
});

it('ergaenzt und entfernt Teammitglieder', function (): void {
    $projekt = Project::factory()->create();
    $anna = User::factory()->create(['name' => 'Anna Berg']);

    $komponente = Livewire::actingAs($this->benutzer)
        ->test(ProjectDetail::class, ['project' => $projekt])
        ->set('newMemberUserId', (string) $anna->id)
        ->set('newMemberRole', 'Projektleitung')
        ->call('addMember')
        ->assertHasNoErrors();

    $mitglied = $projekt->members()->firstOrFail();

    expect($mitglied->user_id)->toBe($anna->id)
        ->and($mitglied->role)->toBe('Projektleitung');

    $komponente->call('removeMember', $mitglied->id);

    expect($projekt->members()->count())->toBe(0);
});

it('archiviert und reaktiviert ein Projekt aus der Detailansicht', function (): void {
    $projekt = Project::factory()->create();

    $komponente = Livewire::actingAs($this->benutzer)
        ->test(ProjectDetail::class, ['project' => $projekt])
        ->call('archive');

    expect($projekt->refresh()->isArchived())->toBeTrue();

    $komponente->call('restore');

    expect($projekt->refresh()->status)->toBe(ProjectStatus::OnHold);
});

it('schuetzt archivierte Projekte auch in der Detailansicht', function (): void {
    $projekt = Project::factory()->archived()->create();
    ProjectPosition::factory()->for($projekt)->create();

    Livewire::actingAs($this->benutzer)
        ->test(ProjectDetail::class, ['project' => $projekt])
        ->assertSee('schreibgeschützt');
});

it('verwaltet frei definierbare Projekttypen', function (): void {
    Livewire::actingAs($this->benutzer)
        ->test(ProjectTypeList::class)
        ->call('create')
        ->set('name', 'Barrierefreiheits-Audit')
        ->set('short_label', 'A11Y')
        ->call('save')
        ->assertHasNoErrors();

    expect(ProjectType::query()->where('name', 'Barrierefreiheits-Audit')->exists())->toBeTrue();
});

it('verlangt Anmeldung fuer die Projektseiten', function (string $name): void {
    $this->get(route($name))->assertRedirect(route('login'));
})->with(['projects.index', 'projects.create', 'project-types.index']);
