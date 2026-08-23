<?php

namespace Database\Seeders;

use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\SaveProductVariant;
use App\Enums\BillingIntervalUnit;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistischer Leistungskatalog fuer die Entwicklung.
 */
class CatalogSeeder extends Seeder
{
    public function __construct(
        private readonly SaveProduct $saveProduct,
        private readonly SaveProductVariant $saveProductVariant,
    ) {}

    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $categories = $this->seedCategories();
        $tags = $this->seedTags();

        foreach ($this->products() as $index => $definition) {
            $kategorie = $categories[$definition['category']];
            $unterkategorie = $definition['subcategory']
                ? $kategorie->children->firstWhere('name', $definition['subcategory'])
                : null;

            $product = ($this->saveProduct)(
                attributes: [
                    'name' => $definition['name'],
                    'internal_name' => Str::slug($definition['name']),
                    'description' => $definition['description'],
                    'category_id' => $kategorie->id,
                    'subcategory_id' => $unterkategorie?->id,
                    'default_purchase_price' => $definition['purchase'],
                    'default_sales_price' => $definition['sales'],
                    'default_billing_interval_unit' => $definition['unit']->value,
                    'default_billing_interval_count' => $definition['count'],
                ],
                tags: [$tags[$index % count($tags)]->id],
                components: $definition['components'],
            );

            foreach ($definition['variants'] as $sortOrder => $variant) {
                ($this->saveProductVariant)($product, [
                    ...$variant,
                    'sort_order' => $sortOrder * 10,
                ]);
            }
        }
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $structure = [
            'Hosting' => ['Webhosting', 'Managed Hosting'],
            'Webentwicklung' => ['Neuentwicklung', 'Wartung'],
            'Cloud' => ['Nextcloud', 'Backup'],
            'Betrieb' => ['Monitoring', 'Sicherheit'],
            'Support' => [],
        ];

        $categories = [];
        $sortOrder = 0;

        foreach ($structure as $name => $children) {
            $category = Category::query()->firstOrCreate(
                ['parent_id' => null, 'name' => $name],
                ['sort_order' => $sortOrder, 'is_active' => true],
            );

            foreach ($children as $childIndex => $childName) {
                Category::query()->firstOrCreate(
                    ['parent_id' => $category->id, 'name' => $childName],
                    ['sort_order' => $childIndex * 10, 'is_active' => true],
                );
            }

            $categories[$name] = $category->load('children');
            $sortOrder += 10;
        }

        return $categories;
    }

    /**
     * @return array<int, Tag>
     */
    private function seedTags(): array
    {
        return collect([
            ['name' => 'Wartungsvertrag', 'color' => 'blue'],
            ['name' => 'Managed', 'color' => 'green'],
            ['name' => 'Selbstverwaltet', 'color' => 'gray'],
            ['name' => 'Kritisch', 'color' => 'red'],
            ['name' => 'Ausbaupotenzial', 'color' => 'amber'],
        ])->map(fn (array $tag) => Tag::query()->firstOrCreate(['name' => $tag['name']], ['color' => $tag['color']]))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'name' => 'Webhosting Standard',
                'description' => 'Shared Hosting mit 10 GB Speicher, PHP und MySQL.',
                'category' => 'Hosting', 'subcategory' => 'Webhosting',
                'purchase' => '2,50', 'sales' => '7,90',
                'unit' => BillingIntervalUnit::Month, 'count' => 1,
                'components' => [
                    ['title' => '10 GB SSD-Speicher'],
                    ['title' => 'Tägliches Backup'],
                    ['title' => 'Kostenloses SSL-Zertifikat'],
                ],
                'variants' => [],
            ],
            [
                'name' => 'Managed Hosting',
                'description' => 'Vollständig betreutes Hosting inklusive Updates und Monitoring.',
                'category' => 'Hosting', 'subcategory' => 'Managed Hosting',
                'purchase' => '18,00', 'sales' => '59,00',
                'unit' => BillingIntervalUnit::Month, 'count' => 1,
                'components' => [
                    ['title' => 'Hosting auf dedizierten Ressourcen'],
                    ['title' => 'Tägliches Backup', 'description' => 'Aufbewahrung 30 Tage'],
                    ['title' => 'Monitoring rund um die Uhr'],
                    ['title' => 'System- und CMS-Updates'],
                    ['title' => '30 Minuten Support', 'sales_price' => '0,00'],
                ],
                'variants' => [
                    ['name' => 'Basic', 'description' => '1 Website, 20 GB Speicher', 'purchase_price' => '12,00', 'sales_price' => '39,00'],
                    ['name' => 'Business', 'description' => '3 Websites, 60 GB Speicher', 'purchase_price' => '18,00', 'sales_price' => '59,00'],
                    ['name' => 'Premium', 'description' => '10 Websites, 200 GB Speicher, Staging', 'purchase_price' => '32,00', 'sales_price' => '99,00'],
                ],
            ],
            [
                'name' => 'Webseitenwartung',
                'description' => 'Regelmäßige Pflege von CMS, Plugins und Inhalten.',
                'category' => 'Webentwicklung', 'subcategory' => 'Wartung',
                'purchase' => '9,00', 'sales' => '49,00',
                'unit' => BillingIntervalUnit::Month, 'count' => 1,
                'components' => [
                    ['title' => 'CMS- und Plugin-Updates'],
                    ['title' => 'Funktionsprüfung nach Updates'],
                    ['title' => 'Monatlicher Statusbericht'],
                ],
                'variants' => [
                    ['name' => 'Klein', 'sales_price' => '29,00'],
                    ['name' => 'Standard', 'sales_price' => '49,00'],
                    ['name' => 'Umfangreich', 'sales_price' => '89,00'],
                ],
            ],
            [
                'name' => 'Nextcloud Hosting',
                'description' => 'Betreute Nextcloud-Instanz in deutschem Rechenzentrum.',
                'category' => 'Cloud', 'subcategory' => 'Nextcloud',
                'purchase' => '6,00', 'sales' => '19,90',
                'unit' => BillingIntervalUnit::Month, 'count' => 1,
                'components' => [
                    ['title' => '100 GB Speicher'],
                    ['title' => 'Bis zu 10 Benutzer'],
                    ['title' => 'Tägliches Backup'],
                ],
                'variants' => [
                    ['name' => '10 Benutzer', 'sales_price' => '19,90'],
                    ['name' => '25 Benutzer', 'sales_price' => '39,90'],
                    ['name' => '50 Benutzer', 'sales_price' => '69,90'],
                ],
            ],
            [
                'name' => 'Offsite-Backup',
                'description' => 'Zusätzliche Sicherung an einem zweiten Standort.',
                'category' => 'Cloud', 'subcategory' => 'Backup',
                'purchase' => '4,00', 'sales' => '12,50',
                'unit' => BillingIntervalUnit::Month, 'count' => 1,
                'components' => [
                    ['title' => 'Tägliche verschlüsselte Sicherung'],
                    ['title' => 'Aufbewahrung 90 Tage'],
                    ['title' => 'Jährlicher Wiederherstellungstest'],
                ],
                'variants' => [],
            ],
            [
                'name' => 'SSL-Zertifikat Business',
                'description' => 'Organisationsvalidiertes Zertifikat mit Warenzeichenprüfung.',
                'category' => 'Betrieb', 'subcategory' => 'Sicherheit',
                'purchase' => '48,00', 'sales' => '119,00',
                'unit' => BillingIntervalUnit::Year, 'count' => 1,
                'components' => [
                    ['title' => 'Organisationsvalidierung'],
                    ['title' => 'Installation und Einrichtung'],
                ],
                'variants' => [],
            ],
            [
                'name' => 'Monitoring',
                'description' => 'Erreichbarkeits- und Zertifikatsüberwachung mit Alarmierung.',
                'category' => 'Betrieb', 'subcategory' => 'Monitoring',
                'purchase' => '1,50', 'sales' => '9,00',
                'unit' => BillingIntervalUnit::Month, 'count' => 1,
                'components' => [
                    ['title' => 'Prüfung alle 60 Sekunden'],
                    ['title' => 'Alarmierung per E-Mail und SMS'],
                    ['title' => 'Zertifikatsablauf-Warnung'],
                ],
                'variants' => [],
            ],
            [
                'name' => 'Supportpauschale',
                'description' => 'Kontingent für kleinere Anpassungen und Rückfragen.',
                'category' => 'Support', 'subcategory' => null,
                'purchase' => '0,00', 'sales' => '75,00',
                'unit' => BillingIntervalUnit::Month, 'count' => 3,
                'components' => [
                    ['title' => '2 Stunden Support je Quartal'],
                    ['title' => 'Reaktionszeit ein Werktag'],
                ],
                'variants' => [],
            ],
            [
                'name' => 'Webentwicklung',
                'description' => 'Projektbezogene Entwicklung nach Aufwand.',
                'category' => 'Webentwicklung', 'subcategory' => 'Neuentwicklung',
                'purchase' => '0,00', 'sales' => '110,00',
                'unit' => BillingIntervalUnit::Once, 'count' => null,
                'components' => [
                    ['title' => 'Konzeption und Umsetzung'],
                    ['title' => 'Abnahme und Übergabe'],
                ],
                'variants' => [],
            ],
            [
                'name' => 'Domain .de',
                'description' => 'Registrierung und Verwaltung einer .de-Domain.',
                'category' => 'Hosting', 'subcategory' => null,
                'purchase' => '4,80', 'sales' => '14,90',
                'unit' => BillingIntervalUnit::Year, 'count' => 1,
                'components' => [
                    ['title' => 'Registrierung und Verlängerung'],
                    ['title' => 'DNS-Verwaltung'],
                ],
                'variants' => [],
            ],
        ];
    }
}
