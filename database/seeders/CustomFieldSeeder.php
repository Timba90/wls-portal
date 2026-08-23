<?php

namespace Database\Seeders;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Database\Seeder;

/**
 * Beispielhafte benutzerdefinierte Felder fuer die Entwicklung.
 */
class CustomFieldSeeder extends Seeder
{
    public function run(): void
    {
        $definitionen = [
            [
                'entity_type' => CustomFieldEntity::Customer,
                'key' => 'kundennummer_provider',
                'name' => 'Kundennummer beim Provider',
                'type' => CustomFieldType::Text,
                'sort_order' => 10,
            ],
            [
                'entity_type' => CustomFieldEntity::Customer,
                'key' => 'betreuungsstufe',
                'name' => 'Betreuungsstufe',
                'type' => CustomFieldType::Select,
                'options' => ['Standard', 'Erweitert', 'Premium'],
                'sort_order' => 20,
            ],
            [
                'entity_type' => CustomFieldEntity::Customer,
                'key' => 'notfallkontakt_erreichbar',
                'name' => 'Notfallkontakt außerhalb der Geschäftszeiten',
                'type' => CustomFieldType::Boolean,
                'sort_order' => 30,
            ],
            [
                'entity_type' => CustomFieldEntity::Product,
                'key' => 'lieferant',
                'name' => 'Lieferant',
                'type' => CustomFieldType::Text,
                'sort_order' => 10,
            ],
            [
                'entity_type' => CustomFieldEntity::CustomerService,
                'key' => 'vertragsnummer',
                'name' => 'Vertragsnummer',
                'type' => CustomFieldType::Text,
                'sort_order' => 10,
            ],
            [
                'entity_type' => CustomFieldEntity::CustomerService,
                'key' => 'kuendigungsfrist',
                'name' => 'Kündigungsfrist',
                'type' => CustomFieldType::Select,
                'options' => ['1 Monat', '3 Monate', '6 Monate', '12 Monate'],
                'sort_order' => 20,
            ],
            [
                'entity_type' => CustomFieldEntity::CustomerService,
                'key' => 'verlaengerung_am',
                'name' => 'Nächste Verlängerung',
                'type' => CustomFieldType::Date,
                'sort_order' => 30,
            ],
        ];

        foreach ($definitionen as $definition) {
            CustomFieldDefinition::query()->firstOrCreate(
                ['entity_type' => $definition['entity_type'], 'key' => $definition['key']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'is_required' => false,
                    'options' => $definition['options'] ?? null,
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
