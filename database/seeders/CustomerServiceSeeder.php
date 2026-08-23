<?php

namespace Database\Seeders;

use App\Actions\Services\CreateCustomerService;
use App\Actions\Services\SetDoNotBill;
use App\Enums\CustomerServiceStatus;
use App\Enums\DoNotBillReason;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Kundenleistungen fuer die Entwicklung.
 *
 * Deckt bewusst alle relevanten Faelle ab: Leistung aus dem Katalog, Leistung
 * mit Variante, individuelle Leistung ohne Katalogartikel, abweichende Preise,
 * verschiedene Status und „bewusst nicht abrechnen".
 */
class CustomerServiceSeeder extends Seeder
{
    public function __construct(
        private readonly CreateCustomerService $createCustomerService,
        private readonly SetDoNotBill $setDoNotBill,
    ) {}

    public function run(): void
    {
        if (CustomerService::query()->exists()) {
            return;
        }

        $customers = Customer::query()->orderBy('id')->get();
        $products = Product::query()->with('variants')->orderBy('id')->get();
        $tags = Tag::query()->orderBy('id')->get();
        $users = User::query()->pluck('id');

        if ($customers->isEmpty() || $products->isEmpty()) {
            return;
        }

        $statuses = [
            CustomerServiceStatus::Active,
            CustomerServiceStatus::Active,
            CustomerServiceStatus::Active,
            CustomerServiceStatus::Planned,
            CustomerServiceStatus::Paused,
            CustomerServiceStatus::Ended,
        ];

        $erzeugt = 0;

        foreach ($customers as $customerIndex => $customer) {
            $anzahl = $customerIndex % 3 === 0 ? 3 : 2;

            for ($i = 0; $i < $anzahl; $i++) {
                $product = $products[($customerIndex + $i) % $products->count()];
                $variant = $product->variants->isNotEmpty()
                    ? $product->variants[($customerIndex + $i) % $product->variants->count()]
                    : null;

                $basispreis = $variant?->effectiveSalesPrice()->cents ?? $product->default_sales_price_cents;

                // Ein Teil der Kunden hat einen abweichenden Preis vereinbart.
                $vereinbart = ($customerIndex + $i) % 4 === 0
                    ? (int) round($basispreis * 0.85)
                    : $basispreis;

                $service = ($this->createCustomerService)(
                    customer: $customer,
                    attributes: [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'name' => "{$product->name} {$customer->short_label}",
                        'billing_label' => $product->name,
                        'status' => $statuses[$erzeugt % count($statuses)],
                        'purchase_price' => number_format(
                            ($variant?->effectivePurchasePrice()->cents ?? $product->default_purchase_price_cents) / 100,
                            2, ',', '',
                        ),
                        'sales_price' => number_format($vereinbart / 100, 2, ',', ''),
                        'billing_interval_unit' => $product->default_billing_interval_unit->value,
                        'billing_interval_count' => $product->default_billing_interval_count,
                        'service_start_date' => now()->subMonths(($erzeugt % 24) + 1)->startOfMonth()->format('Y-m-d'),
                        'billing_start_date' => now()->subMonths(($erzeugt % 24) + 1)->startOfMonth()->format('Y-m-d'),
                        'category_id' => $product->category_id,
                        'subcategory_id' => $product->subcategory_id,
                        'responsible_user_id' => $users->random(),
                    ],
                    tags: $tags->isNotEmpty() ? [$tags[$erzeugt % $tags->count()]->id] : [],
                    components: $product->serviceComponents
                        ->map(fn ($component): array => ['title' => $component->title, 'description' => $component->description])
                        ->all(),
                );

                // Jede zehnte Leistung wird bewusst nicht abgerechnet.
                if ($erzeugt % 10 === 9) {
                    $this->setDoNotBill->mark($service, DoNotBillReason::Included);
                }

                $erzeugt++;
            }

            // Jeder fuenfte Kunde bekommt eine vollstaendig individuelle Leistung.
            if ($customerIndex % 5 === 0) {
                ($this->createCustomerService)(
                    customer: $customer,
                    attributes: [
                        'name' => "Individuelle Betreuung {$customer->short_label}",
                        'billing_label' => 'Individuelle Betreuung',
                        'description' => 'Abweichend vereinbarte Betreuung ohne Katalogartikel.',
                        'status' => CustomerServiceStatus::Active,
                        'purchase_price' => '0,00',
                        'sales_price' => '145,00',
                        'billing_interval_unit' => 'month',
                        'billing_interval_count' => 3,
                        'service_start_date' => now()->subYear()->startOfMonth()->format('Y-m-d'),
                        'responsible_user_id' => $users->random(),
                    ],
                    components: [
                        ['title' => 'Individuelle Absprachen'],
                        ['title' => 'Quartalsgespräch'],
                    ],
                );

                $erzeugt++;
            }
        }
    }
}
