<?php

namespace Database\Seeders;

use App\Actions\Customers\CreateCustomer;
use App\Enums\ContactChannelType;
use App\Enums\CustomerType;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistische deutsche Beispielkunden fuer die Entwicklung.
 */
class CustomerSeeder extends Seeder
{
    /** @var array<int, array{name: string, short: string, code: string}> */
    private array $companies = [
        ['name' => 'Müller Elektrotechnik GmbH', 'short' => 'Müller Elektro', 'code' => 'MUEL'],
        ['name' => 'Bergmann & Sohn Bauunternehmung KG', 'short' => 'Bergmann Bau', 'code' => 'BERG'],
        ['name' => 'Steinbach Steuerberatung', 'short' => 'Steinbach StB', 'code' => 'STEI'],
        ['name' => 'Nordlicht Werbeagentur GmbH', 'short' => 'Nordlicht', 'code' => 'NORD'],
        ['name' => 'Praxis Dr. Kellner MVZ', 'short' => 'Praxis Kellner', 'code' => 'KELL'],
        ['name' => 'Autohaus Reinhardt GmbH & Co. KG', 'short' => 'Autohaus Reinhardt', 'code' => 'REIN'],
        ['name' => 'Hansen Logistik GmbH', 'short' => 'Hansen Logistik', 'code' => 'HANS'],
        ['name' => 'Wiesengrund Gartenbau', 'short' => 'Wiesengrund', 'code' => 'WIES'],
        ['name' => 'Kanzlei Brandt & Partner mbB', 'short' => 'Kanzlei Brandt', 'code' => 'BRAN'],
        ['name' => 'Feinkost Zimmermann e. K.', 'short' => 'Feinkost Zimmermann', 'code' => 'ZIMM'],
        ['name' => 'Technikhaus Oberland GmbH', 'short' => 'Technikhaus', 'code' => 'TECH'],
        ['name' => 'Rheinblick Hotelbetriebs GmbH', 'short' => 'Hotel Rheinblick', 'code' => 'RHEI'],
        ['name' => 'Pflegedienst Sonnenschein GmbH', 'short' => 'Pflege Sonnenschein', 'code' => 'SONN'],
        ['name' => 'Schreinerei Ackermann', 'short' => 'Schreinerei Ackermann', 'code' => 'ACKE'],
        ['name' => 'Bäckerei Grunwald GmbH', 'short' => 'Bäckerei Grunwald', 'code' => 'GRUN'],
        ['name' => 'Vogel Präzisionstechnik GmbH', 'short' => 'Vogel Technik', 'code' => 'VOGE'],
        ['name' => 'Immobilien Lehmann OHG', 'short' => 'Immobilien Lehmann', 'code' => 'LEHM'],
        ['name' => 'Fahrschule Weber', 'short' => 'Fahrschule Weber', 'code' => 'WEBE'],
        ['name' => 'Seehofer Reisen GmbH', 'short' => 'Seehofer Reisen', 'code' => 'SEEH'],
        ['name' => 'Digitalwerk Kramer UG', 'short' => 'Digitalwerk', 'code' => 'KRAM'],
    ];

    /** @var array<int, array{salutation: Salutation, gender: Gender, first: string, last: string, title: ?string}> */
    private array $people = [
        ['salutation' => Salutation::Herr, 'gender' => Gender::Male, 'first' => 'Andreas', 'last' => 'Kowalski', 'title' => null],
        ['salutation' => Salutation::Frau, 'gender' => Gender::Female, 'first' => 'Beate', 'last' => 'Stolzenberg', 'title' => 'Dr.'],
        ['salutation' => Salutation::Herr, 'gender' => Gender::Male, 'first' => 'Christoph', 'last' => 'Bienert', 'title' => null],
        ['salutation' => Salutation::Frau, 'gender' => Gender::Female, 'first' => 'Helena', 'last' => 'Roth', 'title' => null],
        ['salutation' => Salutation::Herr, 'gender' => Gender::Male, 'first' => 'Jürgen', 'last' => 'Falkenberg', 'title' => 'Prof. Dr.'],
    ];

    public function __construct(private readonly CreateCustomer $createCustomer) {}

    public function run(): void
    {
        if (Customer::query()->exists()) {
            return;
        }

        $users = User::query()->pluck('id');

        foreach ($this->companies as $index => $company) {
            ($this->createCustomer)([
                'type' => CustomerType::Company->value,
                'company_name' => $company['name'],
                'short_label' => $company['short'],
                'internal_code' => $company['code'],
                'responsible_user_id' => $index % 4 === 0 ? null : $users->random(),
            ]);
        }

        foreach ($this->people as $index => $person) {
            $slug = Str::slug($person['first'].'.'.$person['last'], '.');

            ($this->createCustomer)([
                'type' => CustomerType::Private->value,
                'salutation' => $person['salutation']->value,
                'academic_title' => $person['title'],
                'first_name' => $person['first'],
                'last_name' => $person['last'],
                'gender' => $person['gender']->value,
                'birth_date' => now()->subYears(30 + $index * 5)->subDays(120)->format('Y-m-d'),
                'short_label' => "{$person['first']} {$person['last']}",
                'internal_code' => Str::upper(Str::substr($person['last'], 0, 4)),
                'responsible_user_id' => $users->random(),
                'emails' => [
                    ['email' => "{$slug}@example.de", 'type' => ContactChannelType::Private->value, 'is_primary' => true],
                    ['email' => "{$slug}@arbeit.example.de", 'type' => ContactChannelType::Business->value],
                ],
                'phones' => [
                    ['number' => '+49 30 '.(2000000 + $index * 4321), 'type' => ContactChannelType::Private->value, 'is_primary' => true],
                    ['number' => '+49 170 '.(3000000 + $index * 5432), 'type' => ContactChannelType::Mobile->value],
                ],
            ]);
        }
    }
}
