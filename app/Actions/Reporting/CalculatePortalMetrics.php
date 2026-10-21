<?php

namespace App\Actions\Reporting;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Support\Money;

/**
 * Kennzahlen für das Dashboard.
 *
 * Umsätze und bisherige Einzelkosten stammen aus aktiven, wiederkehrenden,
 * abrechenbaren Leistungen. Gemeinsame Einkaufskosten werden je Artikel
 * einmal erfasst, einschließlich kostenloser und ungenutzter Anteile.
 *
 * @phpstan-type PortalMetrics array{
 *     activeCustomers: int,
 *     archivedCustomers: int,
 *     activeContacts: int,
 *     products: int,
 *     activeServices: int,
 *     billableServices: int,
 *     doNotBillServices: int,
 *     oneTimeServices: int,
 *     monthlyRevenue: Money,
 *     yearlyRevenue: Money,
 *     monthlyCosts: Money,
 *     yearlyCosts: Money,
 *     monthlyMargin: Money,
 *     yearlyMargin: Money,
 *     marginPercentage: ?float,
 *     costsComplete: bool,
 *     missingCostProducts: int,
 * }
 */
class CalculatePortalMetrics
{
    /**
     * @return PortalMetrics
     */
    public function __invoke(): array
    {
        $summen = $this->sumBillableServices();

        $lizenzkosten = Money::zero();
        $jahreslizenzkosten = Money::zero();
        $fehlendeKosten = 0;

        Product::query()
            ->where('purchase_cost_model', '!=', 'per_service')
            ->where(fn ($query) => $query->where('status', 'active')
                ->orWhereHas('customerServices', fn ($services) => $services->active()))
            ->each(function (Product $product) use (&$lizenzkosten, &$jahreslizenzkosten, &$fehlendeKosten): void {
                $pool = $product->licenseCostSummary();
                $lizenzkosten = $lizenzkosten->plus(Money::fromCents($pool['monthly_cost_cents'] ?? 0));
                $jahreslizenzkosten = $jahreslizenzkosten->plus(Money::fromCents($pool['yearly_cost_cents'] ?? 0));
                $fehlendeKosten += (int) $pool['missing_eur'];
            });

        $monatskosten = $summen['costs']->plus($lizenzkosten);
        $jahreskosten = $summen['costs']->multipliedBy(12)->plus($jahreslizenzkosten);
        $monatsmarge = $summen['revenue']->minus($monatskosten);

        return [
            'activeCustomers' => Customer::query()->active()->count(),
            'archivedCustomers' => Customer::query()->archived()->count(),
            'activeContacts' => Contact::query()->active()->count(),
            'products' => Product::query()->active()->count(),
            'activeServices' => CustomerService::query()->active()->count(),
            'billableServices' => $summen['count'],
            'doNotBillServices' => CustomerService::query()->active()->where('do_not_bill', true)->count(),
            'oneTimeServices' => CustomerService::query()->active()->where('billing_interval_unit', 'once')->count(),
            'monthlyRevenue' => $summen['revenue'],
            'yearlyRevenue' => $summen['revenue']->multipliedBy(12),
            'monthlyCosts' => $monatskosten,
            'yearlyCosts' => $jahreskosten,
            'monthlyMargin' => $monatsmarge,
            'yearlyMargin' => $summen['revenue']->multipliedBy(12)->minus($jahreskosten),
            'costsComplete' => $fehlendeKosten === 0,
            'missingCostProducts' => $fehlendeKosten,
            'marginPercentage' => $summen['revenue']->isZero() || $fehlendeKosten > 0
                ? null
                : round($monatsmarge->cents / $summen['revenue']->cents * 100, 2),
        ];
    }

    /**
     * @return array{revenue: Money, costs: Money, count: int}
     */
    private function sumBillableServices(): array
    {
        $umsatz = Money::zero();
        $kosten = Money::zero();
        $anzahl = 0;

        CustomerService::query()
            ->billable()
            ->with('product')
            ->select([
                'id', 'product_id', 'purchase_price_cents', 'sales_price_cents',
                'billing_interval_unit', 'billing_interval_count',
            ])
            ->chunkById(500, function ($services) use (&$umsatz, &$kosten, &$anzahl): void {
                foreach ($services as $service) {
                    $umsatz = $umsatz->plus($service->monthlyRevenue());
                    // Gemeinsame Kosten werden je Artikel einmal gezaehlt,
                    // einschliesslich ungenutzter und kostenloser Anteile.
                    if (! $service->product?->hasLicenseCostModel()) {
                        $kosten = $kosten->plus($service->monthlyCosts());
                    }
                    $anzahl++;
                }
            });

        return ['revenue' => $umsatz, 'costs' => $kosten, 'count' => $anzahl];
    }
}
