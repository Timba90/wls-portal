<?php

use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\CalculateProjectMetrics;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\RestoreProject;
use App\Actions\Projects\SyncProjectMembers;
use App\Actions\Projects\UpdateProject;
use App\Enums\OperationsStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\ImmutableAttributeException;
use App\Exceptions\ReadOnlyRecordException;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPosition;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('vergibt beim Anlegen eine fortlaufende Projektnummer', function (): void {
    $kunde = Customer::factory()->create();

    $erstes = app(CreateProject::class)($kunde, ['name' => 'Relaunch']);
    $zweites = app(CreateProject::class)($kunde, ['name' => 'Onlineshop']);

    expect($erstes->project_number)->toBe('PR-00001')
        ->and($zweites->project_number)->toBe('PR-00002');
});

it('vergibt eine Nummer nie erneut', function (): void {
    $kunde = Customer::factory()->create();

    app(CreateProject::class)($kunde, ['name' => 'Erstes'])->delete();
    $zweites = app(CreateProject::class)($kunde, ['name' => 'Zweites']);

    expect($zweites->project_number)->toBe('PR-00002');
});

it('haelt die Projektnummer aus der Massenzuweisung heraus', function (): void {
    $projekt = Project::factory()->create();

    expect(fn () => $projekt->update(['project_number' => 'PR-09999']))
        ->toThrow(MassAssignmentException::class);
});

it('laesst die Projektnummer auch bei direkter Zuweisung nicht mehr aendern', function (): void {
    $projekt = Project::factory()->create();
    $nummer = $projekt->project_number;

    $projekt->project_number = 'PR-09999';

    expect(fn () => $projekt->save())->toThrow(ImmutableAttributeException::class)
        ->and($projekt->fresh()->project_number)->toBe($nummer);
});

it('startet ein neues Projekt als geplant', function (): void {
    $projekt = app(CreateProject::class)(Customer::factory()->create(), ['name' => 'Relaunch']);

    expect($projekt->status)->toBe(ProjectStatus::Planned);
});

it('berechnet den Fortschritt aus den Meilensteinen', function (): void {
    $projekt = Project::factory()->create();

    ProjectMilestone::factory()->count(2)->for($projekt)->done()->create();
    ProjectMilestone::factory()->count(2)->for($projekt)->create();

    expect($projekt->load('milestones')->progressPercentage())->toBe(50);
});

it('zaehlt entfallene Meilensteine als erledigt', function (): void {
    $projekt = Project::factory()->create();

    ProjectMilestone::factory()->for($projekt)->done()->create();
    ProjectMilestone::factory()->for($projekt)->create(['status' => 'skipped']);

    // Sonst bliebe der Fortschritt dauerhaft unter hundert Prozent.
    expect($projekt->load('milestones')->progressPercentage())->toBe(100);
});

it('nennt ohne Meilensteine keinen Fortschritt', function (): void {
    $projekt = Project::factory()->create();

    // Ein Prozentwert ohne Meilensteine waere eine Behauptung ohne Grundlage.
    expect($projekt->load('milestones')->progressPercentage())->toBeNull();
});

it('trennt einmaliges und wiederkehrendes Volumen', function (): void {
    $projekt = Project::factory()->create();

    ProjectPosition::factory()->for($projekt)->create(['unit_price_cents' => 150000, 'quantity' => 2]);
    ProjectPosition::factory()->for($projekt)->create(['unit_price_cents' => 50000, 'quantity' => 1]);
    ProjectPosition::factory()->for($projekt)->recurring()->create(['unit_price_cents' => 9900, 'quantity' => 1]);

    $projekt->load('positions');

    expect($projekt->oneTimeVolume()->cents)->toBe(350000)
        ->and($projekt->recurringVolume()->cents)->toBe(9900);
});

it('rechnet Menge mal Einzelpreis auf ganze Cent', function (): void {
    $position = ProjectPosition::factory()->create(['unit_price_cents' => 3333, 'quantity' => 1.5]);

    expect($position->total()->cents)->toBe(5000);
});

it('erkennt ein ueberfaelliges Projekt', function (): void {
    $ueberfaellig = Project::factory()->overdue()->create();
    $puenktlich = Project::factory()->create(['deadline' => now()->addDays(10)]);

    expect($ueberfaellig->isOverdue())->toBeTrue()
        ->and($puenktlich->isOverdue())->toBeFalse();
});

it('meldet abgeschlossene Projekte nicht als ueberfaellig', function (): void {
    $projekt = Project::factory()->completed()->create(['deadline' => now()->subDays(20)]);

    // Die Deadline ist verstrichen, aber das Projekt ist fertig.
    expect($projekt->isOverdue())->toBeFalse();
});

it('archiviert ein Projekt und hebt die Archivierung wieder auf', function (): void {
    $projekt = Project::factory()->create();

    app(ArchiveProject::class)($projekt);

    expect($projekt->refresh()->isArchived())->toBeTrue()
        ->and($projekt->archived_at)->not->toBeNull();

    app(RestoreProject::class)($projekt);

    expect($projekt->refresh()->isArchived())->toBeFalse()
        ->and($projekt->status)->toBe(ProjectStatus::OnHold)
        ->and($projekt->archived_at)->toBeNull();
});

it('schuetzt archivierte Projekte vor Aenderungen', function (): void {
    $projekt = Project::factory()->archived()->create();

    expect(fn () => app(UpdateProject::class)($projekt, ['name' => 'Neuer Name']))
        ->toThrow(ReadOnlyRecordException::class);
});

it('setzt das Team auf die uebergebene Liste', function (): void {
    $projekt = Project::factory()->create();
    $anna = User::factory()->create(['name' => 'Anna Berg']);
    $bruno = User::factory()->create(['name' => 'Bruno Kern']);

    app(SyncProjectMembers::class)($projekt, [
        ['user_id' => $anna->id, 'role' => 'Projektleitung'],
        ['user_id' => $bruno->id, 'role' => 'Entwicklung'],
    ]);

    expect($projekt->load('members')->members)->toHaveCount(2);

    app(SyncProjectMembers::class)($projekt, [['user_id' => $anna->id, 'role' => 'Beratung']]);

    $mitglieder = $projekt->load('members')->members;

    expect($mitglieder)->toHaveCount(1)
        ->and($mitglieder->first()->role)->toBe('Beratung');
});

it('liefert die Kennzahlen der Projektliste', function (): void {
    $offen = Project::factory()->create();
    ProjectPosition::factory()->for($offen)->create(['unit_price_cents' => 100000, 'quantity' => 1]);
    ProjectPosition::factory()->for($offen)->recurring()->create(['unit_price_cents' => 9900, 'quantity' => 1]);

    Project::factory()->create(['backup_status' => OperationsStatus::Critical]);
    Project::factory()->completed()->create();
    Project::factory()->archived()->create();

    $kennzahlen = app(CalculateProjectMetrics::class)();

    expect($kennzahlen['open'])->toBe(2)
        ->and($kennzahlen['revenue']->cents)->toBe(100000)
        ->and($kennzahlen['monthlyRevenue']->cents)->toBe(9900);
});

it('zaehlt jedes offene Projekt mit einer Ampel abseits von gruen', function (): void {
    // Ungeprueft zaehlt mit: niemand hat bestaetigt, dass der Betrieb stimmt.
    Project::factory()->create();

    Project::factory()->create([
        'backup_status' => OperationsStatus::Ok,
        'security_status' => OperationsStatus::Ok,
        'update_status' => OperationsStatus::Ok,
    ]);

    Project::factory()->create([
        'backup_status' => OperationsStatus::Ok,
        'security_status' => OperationsStatus::Critical,
        'update_status' => OperationsStatus::Ok,
    ]);

    // Abgeschlossene und archivierte Projekte betreibt niemand mehr.
    Project::factory()->completed()->create(['backup_status' => OperationsStatus::Critical]);
    Project::factory()->archived()->create(['backup_status' => OperationsStatus::Critical]);

    expect(app(CalculateProjectMetrics::class)()['needsAttention'])->toBe(2);
});

it('setzt jede Betriebsampel auf ungeprueft, solange sie niemand pflegt', function (): void {
    $projekt = Project::factory()->create();

    expect($projekt->operationsStatuses())
        ->toBe([
            'Backup' => OperationsStatus::Unknown,
            'Sicherheit' => OperationsStatus::Unknown,
            'Aktualisierungen' => OperationsStatus::Unknown,
        ]);
});

it('trennt offene Projekte von allen nicht archivierten', function (): void {
    Project::factory()->create(['status' => ProjectStatus::Active]);
    Project::factory()->completed()->create();
    Project::factory()->create(['status' => ProjectStatus::Cancelled]);
    Project::factory()->archived()->create();

    expect(Project::query()->open()->count())->toBe(1)
        ->and(Project::query()->notArchived()->count())->toBe(3);
});

it('bietet frei definierbare Projekttypen', function (): void {
    ProjectType::factory()->create(['name' => 'Barrierefreiheits-Audit']);

    expect(ProjectType::query()->where('name', 'Barrierefreiheits-Audit')->exists())->toBeTrue();
});
