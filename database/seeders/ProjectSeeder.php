<?php

namespace Database\Seeders;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\SaveMilestone;
use App\Actions\Projects\SavePosition;
use App\Actions\Projects\SyncProjectMembers;
use App\Enums\CustomerServiceStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectPositionKind;
use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Projekte fuer die Entwicklung.
 *
 * Deckt die Faelle ab, die in der Oberflaeche unterschiedlich aussehen:
 * laufendes Projekt mit Fortschritt, ueberfaelliges Projekt, Projekt ganz ohne
 * Meilensteine sowie Positionen aus Katalog, Kundenleistung und frei erfasst.
 */
class ProjectSeeder extends Seeder
{
    public function __construct(
        private readonly CreateProject $createProject,
        private readonly SaveMilestone $saveMilestone,
        private readonly SavePosition $savePosition,
        private readonly SyncProjectMembers $syncProjectMembers,
    ) {}

    public function run(): void
    {
        if (Project::query()->exists()) {
            return;
        }

        $this->seedProjectTypes();

        $kunden = Customer::query()->active()->orderBy('id')->take(4)->get();

        if ($kunden->isEmpty()) {
            return;
        }

        $typen = ProjectType::query()->orderBy('sort_order')->get();
        $benutzer = User::query()->orderBy('id')->get();
        $artikel = Product::query()->active()->orderBy('id')->get();

        foreach ($this->blueprints() as $index => $entwurf) {
            $kunde = $kunden[$index % $kunden->count()];

            $projekt = ($this->createProject)($kunde, [
                'name' => $entwurf['name'],
                'description' => $entwurf['description'],
                'project_type_id' => $typen->isNotEmpty() ? $typen[$index % $typen->count()]->id : null,
                'responsible_user_id' => $benutzer->isNotEmpty() ? $benutzer[$index % $benutzer->count()]->id : null,
                'status' => $entwurf['status']->value,
                'start_date' => now()->addDays($entwurf['start'])->toDateString(),
                'deadline' => now()->addDays($entwurf['deadline'])->toDateString(),
                'risk_note' => $entwurf['risk'],
            ]);

            foreach ($entwurf['milestones'] as $reihenfolge => [$name, $status, $faelligIn]) {
                ($this->saveMilestone)($projekt, [
                    'name' => $name,
                    'status' => $status->value,
                    'due_date' => now()->addDays($faelligIn)->toDateString(),
                    'sort_order' => $reihenfolge,
                ]);
            }

            $this->seedPositions($projekt, $artikel, $index);

            if ($benutzer->count() >= 2) {
                ($this->syncProjectMembers)($projekt, [
                    ['user_id' => $benutzer[$index % $benutzer->count()]->id, 'role' => 'Projektleitung'],
                    ['user_id' => $benutzer[($index + 1) % $benutzer->count()]->id, 'role' => 'Entwicklung'],
                ]);
            }
        }
    }

    private function seedProjectTypes(): void
    {
        if (ProjectType::query()->exists()) {
            return;
        }

        // Die drei festen Typen. Die Migration legt sie ebenfalls an, damit sie
        // auch in einem bestehenden Bestand vorhanden sind; hier stehen sie
        // fuer die frische Entwicklungsdatenbank. Erweiterbar bleibt die
        // Liste trotzdem (§61).
        foreach ([
            ['Laravel', 'LAR', 'laravel', 'red'],
            ['Shopify', 'SHOP', 'shopify', 'green'],
            ['WordPress', 'WP', 'wordpress', 'blue'],
        ] as $reihenfolge => [$name, $kuerzel, $symbol, $farbe]) {
            ProjectType::query()->create([
                'name' => $name,
                'short_label' => $kuerzel,
                'icon' => $symbol,
                'color' => $farbe,
                'sort_order' => $reihenfolge,
            ]);
        }
    }

    /**
     * @param  Collection<int, Product>  $artikel
     */
    private function seedPositions(Project $projekt, $artikel, int $index): void
    {
        if ($artikel->isNotEmpty()) {
            $produkt = $artikel[$index % $artikel->count()];

            ($this->savePosition)($projekt, [
                ...($this->savePosition)->suggestionFromProduct($produkt),
                'product_id' => $produkt->id,
                'quantity' => 1,
                'status' => CustomerServiceStatus::Active->value,
            ]);
        }

        $leistung = CustomerService::query()
            ->where('customer_id', $projekt->customer_id)
            ->orderBy('id')
            ->first();

        if ($leistung) {
            ($this->savePosition)($projekt, [
                ...($this->savePosition)->suggestionFromService($leistung),
                'customer_service_id' => $leistung->id,
                'quantity' => 1,
                'status' => CustomerServiceStatus::Active->value,
            ]);
        }

        // Bewusst unterschiedlich: sonst zeigt die Liste bei jedem Projekt
        // denselben Betrag, und die Spalte sagt nichts aus.
        ($this->savePosition)($projekt, [
            'name' => 'Projektleitung und Abstimmung',
            'kind' => ProjectPositionKind::OneTime->value,
            'quantity' => 8 + $index * 6,
            'unit_price' => '95,00',
            'status' => CustomerServiceStatus::Active->value,
        ]);

        ($this->savePosition)($projekt, [
            'name' => 'Umsetzung',
            'kind' => ProjectPositionKind::OneTime->value,
            'quantity' => 20 + $index * 14,
            'unit_price' => '110,00',
            'status' => CustomerServiceStatus::Active->value,
        ]);
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     description: string,
     *     status: ProjectStatus,
     *     start: int,
     *     deadline: int,
     *     risk: ?string,
     *     milestones: array<int, array{0: string, 1: MilestoneStatus, 2: int}>,
     * }>
     */
    private function blueprints(): array
    {
        return [
            [
                'name' => 'Relaunch Unternehmenswebseite',
                'description' => 'Neuer Auftritt inklusive Redaktionssystem und Umzug der Bestandsinhalte.',
                'status' => ProjectStatus::Active,
                'start' => -45,
                'deadline' => 40,
                'risk' => 'Die Inhalte kommen verspätet aus der Fachabteilung.',
                'milestones' => [
                    ['Kickoff', MilestoneStatus::Done, -45],
                    ['Konzept abgenommen', MilestoneStatus::Done, -20],
                    ['Design abgenommen', MilestoneStatus::InProgress, 7],
                    ['Redaktionsschluss', MilestoneStatus::Open, 25],
                    ['Livegang', MilestoneStatus::Open, 40],
                ],
            ],
            [
                'name' => 'Shop-Anbindung Warenwirtschaft',
                'description' => 'Abgleich von Beständen und Preisen zwischen Shop und Warenwirtschaft.',
                'status' => ProjectStatus::Active,
                'start' => -90,
                'deadline' => -12,
                'risk' => 'Die Schnittstelle des Warenwirtschaftssystems ist unvollständig dokumentiert.',
                'milestones' => [
                    ['Schnittstelle spezifiziert', MilestoneStatus::Done, -70],
                    ['Testabgleich', MilestoneStatus::InProgress, -12],
                    ['Produktivschaltung', MilestoneStatus::Open, 5],
                ],
            ],
            [
                'name' => 'Kundenportal Phase 1',
                'description' => 'Erste Ausbaustufe: Anmeldung, Stammdaten und Belegübersicht.',
                'status' => ProjectStatus::Planned,
                'start' => 14,
                'deadline' => 120,
                'risk' => null,
                'milestones' => [],
            ],
            [
                'name' => 'Wartung und Betrieb 2026',
                'description' => 'Laufende Betreuung mit festem monatlichem Kontingent.',
                'status' => ProjectStatus::OnHold,
                'start' => -10,
                'deadline' => 300,
                'risk' => 'Wartet auf die Freigabe des Jahresbudgets.',
                'milestones' => [
                    ['Betriebsübergabe', MilestoneStatus::Skipped, -5],
                    ['Quartalsbericht I', MilestoneStatus::Open, 60],
                ],
            ],
        ];
    }
}
