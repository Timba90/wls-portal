<?php

namespace App\Actions\Search;

use App\Enums\CustomerServiceStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Globale Suche über Kunden, Ansprechpartner, Katalogartikel und
 * Kundenleistungen.
 *
 * Archivierte Datensätze sind ausgeschlossen — dafür gibt es die
 * Archivansichten.
 *
 * @phpstan-type SearchResult array{typ: string, name: string, zusatz: string, url: string}
 */
class GlobalSearch
{
    private const LIMIT_JE_TYP = 5;

    /**
     * @return Collection<int, array{typ: string, treffer: Collection<int, array{name: string, zusatz: string, url: string}>}>
     */
    public function __invoke(string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return collect([
            ['typ' => 'Kunden', 'treffer' => $this->customers($term)],
            ['typ' => 'Ansprechpartner', 'treffer' => $this->contacts($term)],
            ['typ' => 'Artikel / Leistungen', 'treffer' => $this->products($term)],
            ['typ' => 'Kundenleistungen', 'treffer' => $this->customerServices($term)],
        ])->filter(fn (array $gruppe): bool => $gruppe['treffer']->isNotEmpty())->values();
    }

    /**
     * @return Collection<int, array{name: string, zusatz: string, url: string}>
     */
    private function customers(string $term): Collection
    {
        $like = '%'.$term.'%';

        return Customer::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where('customer_number', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('short_label', 'like', $like)
                ->orWhere('internal_code', 'like', $like)
                ->orWhereHas('emailAddresses', fn (Builder $emails) => $emails->where('email', 'like', $like)))
            ->orderBy('customer_number')
            ->limit(self::LIMIT_JE_TYP)
            ->get()
            ->map(fn (Customer $customer): array => [
                'name' => $customer->displayName(),
                'zusatz' => "{$customer->customer_number} · {$customer->short_label}",
                'url' => route('customers.show', $customer),
            ]);
    }

    /**
     * @return Collection<int, array{name: string, zusatz: string, url: string}>
     */
    private function contacts(string $term): Collection
    {
        $like = '%'.$term.'%';

        return Contact::query()
            ->active()
            ->with(['assignments.customer', 'emailAddresses'])
            ->where(fn (Builder $query) => $query
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhereHas('emailAddresses', fn (Builder $emails) => $emails->where('email', 'like', $like))
                ->orWhereHas('phoneNumbers', fn (Builder $phones) => $phones->where('number', 'like', $like)))
            ->orderBy('last_name')
            ->limit(self::LIMIT_JE_TYP)
            ->get()
            ->map(fn (Contact $contact): array => [
                'name' => $contact->fullName(),
                'zusatz' => collect([
                    $contact->primaryEmailAddress()?->email,
                    $contact->assignments->first()?->customer?->short_label,
                ])->filter()->implode(' · '),
                'url' => route('contacts.show', $contact),
            ]);
    }

    /**
     * @return Collection<int, array{name: string, zusatz: string, url: string}>
     */
    private function products(string $term): Collection
    {
        $like = '%'.$term.'%';

        return Product::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('internal_name', 'like', $like))
            ->orderBy('name')
            ->limit(self::LIMIT_JE_TYP)
            ->get()
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'zusatz' => $product->internal_name,
                'url' => route('products.show', $product),
            ]);
    }

    /**
     * @return Collection<int, array{name: string, zusatz: string, url: string}>
     */
    private function customerServices(string $term): Collection
    {
        $like = '%'.$term.'%';

        return CustomerService::query()
            ->with('customer')
            ->whereNot('status', CustomerServiceStatus::Archived)
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('billing_label', 'like', $like))
            ->orderBy('name')
            ->limit(self::LIMIT_JE_TYP)
            ->get()
            ->map(fn (CustomerService $service): array => [
                'name' => $service->name,
                'zusatz' => "{$service->customer->short_label} · {$service->salesPrice()->format()} · {$service->billingInterval()->label()}",
                'url' => route('customer-services.show', [$service->customer, $service]),
            ]);
    }
}
