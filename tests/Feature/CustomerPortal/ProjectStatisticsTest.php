<?php

use App\Actions\CustomerPortal\CalculateProjectStatistics;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\PortalUser;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPosition;
use App\Models\Ticket;

beforeEach(function (): void {
    $this->kunde = Customer::factory()->create();
    $this->zugang = PortalUser::factory()->for($this->kunde)->create();
});

it('berechnet Fortschritt und Meilensteine ueber die laufenden Projekte', function (): void {
    $a = Project::factory()->for($this->kunde)->create();
    ProjectMilestone::factory()->for($a)->create(['status' => MilestoneStatus::Done]);
    ProjectMilestone::factory()->for($a)->create(['status' => MilestoneStatus::Open, 'due_date' => now()->subDay()]);

    $b = Project::factory()->for($this->kunde)->create();
    ProjectMilestone::factory()->for($b)->create(['status' => MilestoneStatus::Done]);

    Project::factory()->for($this->kunde)->completed()->create();
    Project::factory()->for($this->kunde)->archived()->create();
    Project::factory()->create();

    $statistik = app(CalculateProjectStatistics::class)->forCustomer($this->kunde);

    expect($statistik['open'])->toBe(2)
        ->and($statistik['completed'])->toBe(1)
        ->and($statistik['averageProgress'])->toBe(75)
        ->and($statistik['milestonesDone'])->toBe(2)
        ->and($statistik['milestonesTotal'])->toBe(3)
        ->and($statistik['overdueMilestones'])->toBe(1)
        ->and($statistik['upcomingMilestones'])->toHaveCount(1);
});

it('zaehlt Tickets je Projekt', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create();
    Ticket::factory()->for($this->kunde)->create(['project_id' => $projekt->id]);
    Ticket::factory()->for($this->kunde)->closed()->create(['project_id' => $projekt->id]);

    $statistik = app(CalculateProjectStatistics::class)->forProject($projekt);

    expect($statistik['ticketsOpen'])->toBe(1)->and($statistik['ticketsTotal'])->toBe(2);
});

it('zeigt eigene Projekte ohne Preise und verweigert fremde und archivierte', function (): void {
    $projekt = Project::factory()->for($this->kunde)->create(['name' => 'Relaunch Webseite']);
    ProjectPosition::factory()->for($projekt)->create(['name' => 'Interne Kalkulationsposition', 'unit_price_cents' => 1234567]);
    ProjectMilestone::factory()->for($projekt)->create(['name' => 'Designfreigabe']);
    $fremd = Project::factory()->create(['name' => 'Fremdes Projekt']);
    $archiviert = Project::factory()->for($this->kunde)->archived()->create();

    $this->actingAs($this->zugang, 'customer');

    $this->get(route('customer-portal.projects.index'))
        ->assertOk()
        ->assertSee('Relaunch Webseite')
        ->assertDontSee('Fremdes Projekt');

    $this->get(route('customer-portal.projects.show', $projekt))
        ->assertOk()
        ->assertSee('Designfreigabe')
        ->assertDontSee('Interne Kalkulationsposition')
        ->assertDontSee('12.345,67');

    $this->get(route('customer-portal.projects.show', $fremd))->assertNotFound();
    $this->get(route('customer-portal.projects.show', $archiviert))->assertNotFound();
});

it('zeigt die Startseite mit Kennzahlen', function (): void {
    Project::factory()->for($this->kunde)->create(['status' => ProjectStatus::Active, 'name' => 'Onlineshop Muster']);
    Ticket::factory()->for($this->kunde)->create(['subject' => 'Frage zur Rechnung']);

    $this->actingAs($this->zugang, 'customer')
        ->get(route('customer-portal.dashboard'))
        ->assertOk()
        ->assertSee('Onlineshop Muster')
        ->assertSee('Frage zur Rechnung')
        ->assertSee('Abrechnung noch nicht eingerichtet');
});

it('rendert Profil und Ticketformular', function (): void {
    $this->actingAs($this->zugang, 'customer');

    $this->get(route('customer-portal.profile'))->assertOk()->assertSee($this->zugang->email);
    $this->get(route('customer-portal.tickets.create'))->assertOk()->assertSee('Anliegen melden');
});
