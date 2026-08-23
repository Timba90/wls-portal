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
 * In Umsatz, Kosten und Marge fließen ausschließlich aktive, wiederkehrende
 * Leistungen ohne die Kennzeichnung „Bewusst nicht abrechnen" ein. Einmalige
 * Leistungen wiederholen sich nicht und bleiben deshalb außen vor.
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

        $monatsmarge = $summen['revenue']->minus($summen['costs']);

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
            'monthlyCosts' => $summen['costs'],
            'yearlyCosts' => $summen['costs']->multipliedBy(12),
            'monthlyMargin' => $monatsmarge,
            'yearlyMargin' => $monatsmarge->multipliedBy(12),
            'marginPercentage' => $summen['revenue']->isZero()
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
            ->select([
                'id', 'purchase_price_cents', 'sales_price_cents',
                'billing_interval_unit', 'billing_interval_count',
            ])
            ->chunkById(500, function ($services) use (&$umsatz, &$kosten, &$anzahl): void {
                foreach ($services as $service) {
                    $umsatz = $umsatz->plus($service->monthlyRevenue());
                    $kosten = $kosten->plus($service->monthlyCosts());
                    $anzahl++;
                }
            });

        return ['revenue' => $umsatz, 'costs' => $kosten, 'count' => $anzahl];
    }
}
