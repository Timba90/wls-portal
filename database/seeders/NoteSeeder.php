<?php

namespace Database\Seeders;

use App\Enums\NoteCategory;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Beispielnotizen fuer die Entwicklung.
 */
class NoteSeeder extends Seeder
{
    public function run(): void
    {
        if (Note::query()->exists()) {
            return;
        }

        $users = User::query()->pluck('id');

        if ($users->isEmpty()) {
            return;
        }

        $kundennotizen = [
            [NoteCategory::General, 'Ansprechpartner bevorzugt Rückrufe am Vormittag.'],
            [NoteCategory::Billing, 'Rechnungen bitte gesammelt zum Quartalsende versenden.'],
            [NoteCategory::Technical, 'Zugangsdaten liegen im internen Passwortmanager.'],
            [NoteCategory::Contract, 'Rahmenvertrag verlängert sich jährlich automatisch.'],
        ];

        Customer::query()->orderBy('id')->take(8)->get()
            ->each(function (Customer $customer, int $index) use ($kundennotizen, $users): void {
                [$kategorie, $text] = $kundennotizen[$index % count($kundennotizen)];

                $customer->notes()->create([
                    'category' => $kategorie,
                    'body' => $text,
                    'user_id' => $users->random(),
                ]);
            });

        CustomerService::query()->orderBy('id')->take(6)->get()
            ->each(function (CustomerService $service) use ($users): void {
                $service->notes()->create([
                    'category' => NoteCategory::Technical,
                    'body' => 'Leistungsumfang wurde beim letzten Termin gemeinsam durchgesprochen.',
                    'user_id' => $users->random(),
                ]);
            });
    }
}
