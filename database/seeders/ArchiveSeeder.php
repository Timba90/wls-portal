<?php

namespace Database\Seeders;

use App\Enums\CatalogStatus;
use App\Enums\CustomerServiceStatus;
use App\Enums\CustomerStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Archiviert einige Datensaetze, damit die Archivansichten in der Entwicklung
 * nicht leer sind.
 */
class ArchiveSeeder extends Seeder
{
    public function run(): void
    {
        if (Customer::query()->archived()->exists()) {
            return;
        }

        CustomerService::query()
            ->orderByDesc('id')
            ->take(3)
            ->get()
            ->each(fn (CustomerService $service) => $service->forceFill([
                'status' => CustomerServiceStatus::Archived,
                'archived_at' => now()->subMonths(2),
            ])->save());

        // Nur Kunden ohne aktive Leistungen lassen sich archivieren.
        Customer::query()
            ->active()
            ->whereDoesntHave('services', fn ($query) => $query->active())
            ->take(2)
            ->get()
            ->each(fn (Customer $customer) => $customer->forceFill([
                'status' => CustomerStatus::Archived,
                'archived_at' => now()->subMonth(),
            ])->save());

        Contact::query()
            ->active()
            ->orderByDesc('id')
            ->take(2)
            ->get()
            ->each(fn (Contact $contact) => $contact->forceFill(['archived_at' => now()->subWeeks(3)])->save());

        Product::query()
            ->active()
            ->orderByDesc('id')
            ->take(1)
            ->get()
            ->each(fn (Product $product) => $product->forceFill([
                'status' => CatalogStatus::Archived,
                'archived_at' => now()->subMonths(4),
            ])->save());
    }
}
