<?php

namespace Database\Seeders;

use App\Models\ContactRole;
use Illuminate\Database\Seeder;

/**
 * Ansprechpartnerrollen sind frei definierbar; diese Auswahl dient als
 * praktikabler Startbestand.
 */
class ContactRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Geschäftsführung', 'description' => 'Vertretungsberechtigung und Vertragsfragen'],
            ['name' => 'Technik', 'description' => 'Technische Ansprechperson für Systeme und Störungen'],
            ['name' => 'Buchhaltung', 'description' => 'Rechnungen, Zahlungen und Mahnwesen'],
            ['name' => 'Einkauf', 'description' => 'Beschaffung und Angebotsprüfung'],
            ['name' => 'Marketing', 'description' => 'Inhalte, Kampagnen und Außendarstellung'],
            ['name' => 'IT-Leitung', 'description' => 'Verantwortung für die IT-Infrastruktur'],
            ['name' => 'Datenschutz', 'description' => 'Datenschutz und Auftragsverarbeitung'],
        ];

        foreach ($roles as $index => $role) {
            ContactRole::query()->firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description'], 'sort_order' => $index * 10, 'is_active' => true],
            );
        }
    }
}
