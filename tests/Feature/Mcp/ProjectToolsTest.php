<?php

use App\Actions\Maintenance\DeletePermanently;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectPositionKind;
use App\Enums\ProjectStatus;
use App\Mcp\Servers\PortalServer;
use App\Mcp\Tools\Projects\MeilensteinSpeichern;
use App\Mcp\Tools\Projects\PositionSpeichern;
use App\Mcp\Tools\Projects\ProjektArchivieren;
use App\Mcp\Tools\Projects\ProjekteSuchen;
use App\Mcp\Tools\Projects\ProjektLesen;
use App\Mcp\Tools\Projects\ProjektLoeschen;
use App\Mcp\Tools\Projects\ProjektSpeichern;
use App\Mcp\Tools\Projects\ProjektTeamSetzen;
use App\Mcp\Tools\Projects\ProjekttypenVerwalten;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPosition;
use App\Models\ProjectType;
use App\Models\User;

beforeEach(function (): void {
    $this->benutzer = User::factory()->create();
    $this->kunde = Customer::factory()->company()->create(['company_name' => 'Nordlicht Medien']);
});

it('sucht standardmaessig nur offene Projekte', function (): void {
    Project::factory()->for($this->kunde)->create(['name' => 'Laufender Relaunch', 'status' => ProjectStatus::Active]);
    Project::factory()->completed()->create(['name' => 'Fertiger Shop']);

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjekteSuchen::class, [])
        ->assertOk()
        ->assertSee('Laufender Relaunch')
        ->assertDontSee('Fertiger Shop');
});

it('legt ein Projekt an und vergibt die Projektnummer', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektSpeichern::class, [
            'kunde_id' => $this->kunde->id,
            'name' => 'Portal Nordlicht',
        ])
        ->assertOk()
        ->assertSee('PR-00001');

    $projekt = Project::query()->firstOrFail();

    expect($projekt->customer_id)->toBe($this->kunde->id)
        ->and($projekt->status)->toBe(ProjectStatus::Planned);
});

it('verlangt zum Anlegen einen Kunden', function (): void {
    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektSpeichern::class, ['name' => 'Projekt ohne Kunde'])
        ->assertHasErrors();

    expect(Project::query()->count())->toBe(0);
});

it('laesst den Status Archiviert nicht ueber das Speichern setzen', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektSpeichern::class, [
            'id' => $projekt->id,
            'name' => $projekt->name,
            'status' => ProjectStatus::Archived->value,
        ])
        ->assertHasErrors();

    expect($projekt->refresh()->isArchived())->toBeFalse();
});

it('schuetzt archivierte Projekte vor Aenderungen', function (): void {
    $projekt = Project::factory()->archived()->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektSpeichern::class, ['id' => $projekt->id, 'name' => 'Neuer Name'])
        ->assertHasErrors();
});

it('archiviert ein Projekt und reaktiviert es als pausiert', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektArchivieren::class, ['id' => $projekt->id])
        ->assertOk();

    expect($projekt->refresh()->isArchived())->toBeTrue();

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektArchivieren::class, ['id' => $projekt->id, 'reaktivieren' => true])
        ->assertOk();

    expect($projekt->refresh()->status)->toBe(ProjectStatus::OnHold);
});

it('liefert Fortschritt und Volumen aus echten Daten', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();

    ProjectMilestone::factory()->for($projekt)->done()->create();
    ProjectMilestone::factory()->for($projekt)->create();
    ProjectPosition::factory()->for($projekt)->create(['unit_price_cents' => 100000, 'quantity' => 2]);

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektLesen::class, ['id' => $projekt->id])
        ->assertOk()
        ->assertSee('"fortschritt_prozent":50')
        ->assertSee('200000');
});

it('nennt ohne Meilensteine keinen Fortschritt', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektLesen::class, ['id' => $projekt->id])
        ->assertOk()
        ->assertSee('"fortschritt_prozent":null');
});

it('pflegt Meilensteine und rechnet den Fortschritt neu', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(MeilensteinSpeichern::class, [
            'projekt_id' => $projekt->id,
            'name' => 'Konzept abgenommen',
        ])
        ->assertOk();

    $meilenstein = $projekt->milestones()->firstOrFail();

    expect($meilenstein->status)->toBe(MilestoneStatus::Open);

    PortalServer::actingAs($this->benutzer)
        ->tool(MeilensteinSpeichern::class, [
            'projekt_id' => $projekt->id,
            'id' => $meilenstein->id,
            'status' => MilestoneStatus::Done->value,
        ])
        ->assertOk()
        ->assertSee('"fortschritt_prozent":100');

    // Der Name bleibt erhalten, obwohl nur der Status übergeben wurde.
    expect($meilenstein->refresh()->name)->toBe('Konzept abgenommen');

    PortalServer::actingAs($this->benutzer)
        ->tool(MeilensteinSpeichern::class, [
            'projekt_id' => $projekt->id,
            'id' => $meilenstein->id,
            'entfernen' => true,
        ])
        ->assertOk();

    expect($projekt->milestones()->count())->toBe(0);
});

it('uebernimmt Name und Preis eines Katalogartikels in die Position', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();
    $artikel = Product::factory()->create([
        'name' => 'Hosting Business',
        'default_sales_price_cents' => 15000,
    ]);

    PortalServer::actingAs($this->benutzer)
        ->tool(PositionSpeichern::class, [
            'projekt_id' => $projekt->id,
            'produkt_id' => $artikel->id,
            'art' => ProjectPositionKind::OneTime->value,
            'menge' => 2,
        ])
        ->assertOk();

    $position = $projekt->positions()->firstOrFail();

    expect($position->name)->toBe('Hosting Business')
        ->and($position->unit_price_cents)->toBe(15000)
        ->and($position->total()->cents)->toBe(30000);
});

it('nimmt keine Kundenleistung eines anderen Kunden als Herkunft', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();
    $fremdeLeistung = CustomerService::factory()->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(PositionSpeichern::class, [
            'projekt_id' => $projekt->id,
            'kundenleistung_id' => $fremdeLeistung->id,
        ])
        ->assertHasErrors();

    expect($projekt->positions()->count())->toBe(0);
});

it('trennt einmaliges und wiederkehrendes Volumen', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(PositionSpeichern::class, [
            'projekt_id' => $projekt->id,
            'name' => 'Umsetzung',
            'art' => ProjectPositionKind::OneTime->value,
            'menge' => 10,
            'einzelpreis_cents' => 11000,
        ])
        ->assertOk();

    PortalServer::actingAs($this->benutzer)
        ->tool(PositionSpeichern::class, [
            'projekt_id' => $projekt->id,
            'name' => 'Betreuung',
            'art' => ProjectPositionKind::Recurring->value,
            'einzelpreis_cents' => 9900,
        ])
        ->assertOk();

    $projekt->load('positions');

    expect($projekt->oneTimeVolume()->cents)->toBe(110000)
        ->and($projekt->recurringVolume()->cents)->toBe(9900);
});

it('setzt das Team auf die uebergebene Liste', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();
    $anna = User::factory()->create(['name' => 'Anna Berg']);
    $bruno = User::factory()->create(['name' => 'Bruno Kern']);

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektTeamSetzen::class, [
            'projekt_id' => $projekt->id,
            'mitglieder' => [
                ['benutzer_id' => $anna->id, 'rolle' => 'Projektleitung'],
                ['benutzer_id' => $bruno->id],
            ],
        ])
        ->assertOk();

    expect($projekt->members()->count())->toBe(2);

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektTeamSetzen::class, ['projekt_id' => $projekt->id, 'mitglieder' => []])
        ->assertOk();

    expect($projekt->members()->count())->toBe(0);
});

it('listet und ergaenzt frei definierbare Projekttypen', function (): void {
    ProjectType::factory()->create(['name' => 'Webseite']);

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjekttypenVerwalten::class, [])
        ->assertOk()
        ->assertSee('Webseite');

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjekttypenVerwalten::class, ['name' => 'Barrierefreiheits-Audit', 'kuerzel' => 'A11Y'])
        ->assertOk();

    expect(ProjectType::query()->where('name', 'Barrierefreiheits-Audit')->exists())->toBeTrue();
});

it('weist doppelte Projekttypnamen zurueck', function (): void {
    ProjectType::factory()->create(['name' => 'Webseite']);

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjekttypenVerwalten::class, ['name' => 'Webseite'])
        ->assertHasErrors();

    expect(ProjectType::query()->where('name', 'Webseite')->count())->toBe(1);
});

it('loescht ein Projekt nur mit passender Bestaetigung', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();
    ProjectMilestone::factory()->for($projekt)->create();

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektLoeschen::class, ['id' => $projekt->id, 'bestaetigung' => 'PR-99999'])
        ->assertHasErrors();

    expect(Project::query()->whereKey($projekt->id)->exists())->toBeTrue();

    PortalServer::actingAs($this->benutzer)
        ->tool(ProjektLoeschen::class, ['id' => $projekt->id, 'bestaetigung' => $projekt->project_number])
        ->assertOk();

    expect(Project::query()->whereKey($projekt->id)->exists())->toBeFalse()
        ->and(ProjectMilestone::query()->count())->toBe(0);
});

it('raeumt beim endgueltigen Loeschen eines Kunden dessen Projekte mit ab', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();
    ProjectPosition::factory()->for($projekt)->create();

    // Ohne diese Kaskade wuerde der Fremdschluessel das Loeschen blockieren.
    app(DeletePermanently::class)($this->kunde);

    expect(Project::query()->count())->toBe(0)
        ->and(ProjectPosition::query()->count())->toBe(0);
});
