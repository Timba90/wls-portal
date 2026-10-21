<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class LicenseCostCalculator
{
    /** @var array<string, array<string, mixed>> */
    private array $summaries = [];

    public function invalidate(): void
    {
        $this->summaries = [];
    }

    /** @return array<string, mixed> */
    public function summary(Product $product): array
    {
        $key = json_encode([$product->getKey(), $product->only([
            'purchase_cost_model', 'purchase_usage_unit', 'purchase_amount_cents', 'purchase_currency',
            'purchase_eur_amount_cents', 'purchase_interval_unit', 'purchase_interval_count', 'purchase_units_per_pack',
        ])], JSON_THROW_ON_ERROR);

        return $this->summaries[$key] ??= $this->calculate($product);
    }

    /** @return array<string, mixed> */
    private function calculate(Product $product): array
    {
        $services = $product->customerServices()->active()->orderBy('id')->get();
        $groups = $services->groupBy(fn ($service) => match ($product->purchase_usage_unit) {
            'customer' => 'customer:'.$service->customer_id,
            'project' => $service->usage_project_id ? 'project:'.$service->usage_project_id : 'service:'.$service->id,
            default => 'service:'.$service->id,
        });
        $weights = $groups->map(fn ($group) => match ($product->purchase_usage_unit) {
            'customer' => 1,
            'project' => $group->first()->usage_project_id ? 1 : $group->sum('usage_quantity'),
            default => $group->sum('usage_quantity'),
        })->all();
        $usage = array_sum($weights);
        $packages = match ($product->purchase_cost_model) {
            'per_unit' => $usage,
            'shared_pack' => max(1, (int) ceil($usage / max(1, $product->purchase_units_per_pack))),
            'shared_unlimited' => 1,
            default => 0,
        };
        $maximumAmount = 1000000000000;
        if ($usage > 1000000000 || ($packages > 0 && (
            $product->purchase_amount_cents > intdiv($maximumAmount, $packages)
            || ($product->purchase_eur_amount_cents ?? 0) > intdiv($maximumAmount, $packages)
        ))) {
            throw ValidationException::withMessages(['purchase_amount_cents' => 'Die gesamten Lizenzkosten oder die Nutzung sind zu hoch.']);
        }
        $amount = $product->purchase_amount_cents * $packages;
        $eur = $product->purchase_currency === 'EUR' ? $amount : ($product->purchase_eur_amount_cents === null ? null : $product->purchase_eur_amount_cents * $packages);
        $months = $product->purchase_interval_count * ($product->purchase_interval_unit->value === 'year' ? 12 : 1);
        $monthly = $eur === null ? null : (int) round($eur / $months);
        $yearly = $eur === null ? null : (int) round($eur * 12 / $months);
        $monthlyGroups = $this->allocate($monthly, $weights);
        $yearlyGroups = $this->allocate($yearly, $weights);
        $allocations = [];
        foreach ($groups as $key => $group) {
            $serviceWeights = $group->mapWithKeys(fn ($service) => [$service->id => 1])->all();
            $monthlyServices = $this->allocate($monthlyGroups[$key], $serviceWeights);
            $yearlyServices = $this->allocate($yearlyGroups[$key], $serviceWeights);
            foreach ($group as $service) {
                $allocations[$service->id] = ['monthly_cents' => $monthlyServices[$service->id], 'yearly_cents' => $yearlyServices[$service->id]];
            }
        }

        return [
            'usage_units' => $usage, 'packages' => $packages, 'amount_cents' => $amount,
            'currency' => $product->purchase_currency, 'eur_amount_cents' => $eur,
            'monthly_cost_cents' => $monthly, 'yearly_cost_cents' => $yearly,
            'unallocated_monthly_cents' => $usage === 0 ? $monthly : 0,
            'unallocated_yearly_cents' => $usage === 0 ? $yearly : 0,
            'missing_eur' => $eur === null, 'allocations' => $allocations,
        ];
    }

    /**
     * @param  array<int, int>  $weights
     * @return array<int, int|null>
     */
    private function allocate(?int $amount, array $weights): array
    {
        if ($amount === null) {
            return array_fill_keys(array_keys($weights), null);
        }
        $totalWeight = array_sum($weights);
        $result = [];
        foreach ($weights as $id => $weight) {
            $result[$id] = intdiv($amount, $totalWeight) * $weight
                + intdiv(($amount % $totalWeight) * $weight, $totalWeight);
        }
        $remaining = $amount - array_sum($result);
        foreach ($weights as $id => $weight) {
            if ($remaining === 0) {
                break;
            }
            $result[$id]++;
            $remaining--;
        }

        return $result;
    }
}
