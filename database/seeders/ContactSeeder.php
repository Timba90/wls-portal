<?php

namespace Database\Seeders;

use App\Actions\Contacts\CreateContact;
use App\Enums\ContactChannelType;
use App\Enums\ContactMethod;
use App\Enums\CustomerType;
use App\Enums\Gender;
use App\Enums\Salutation;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ansprechpartner fuer die Firmenkunden.
 *
 * Zwei Ansprechpartner werden bewusst mehreren Kunden zugeordnet, damit die
 * n:m-Beziehung und rollenabhaengige Unterschiede sichtbar sind.
 */
class ContactSeeder extends Seeder
{
    /** @var array<int, array{first: string, last: string, gender: Gender, title: ?string}> */
    private array $people = [
        ['first' => 'Thomas', 'last' => 'Lindner', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Katrin', 'last' => 'Vogt', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Michael', 'last' => 'Sauerbier', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Petra', 'last' => 'Neumann', 'gender' => Gender::Female, 'title' => 'Dr.'],
        ['first' => 'Stefan', 'last' => 'Kohl', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Anja', 'last' => 'Weiß', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Dirk', 'last' => 'Ostermann', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Silke', 'last' => 'Baumgart', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Ralf', 'last' => 'Hennig', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Miriam', 'last' => 'Kluge', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Bernd', 'last' => 'Schuster', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Claudia', 'last' => 'Reimann', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Oliver', 'last' => 'Frenzel', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Nadine', 'last' => 'Pohlmann', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Sven', 'last' => 'Dittmar', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Ulrike', 'last' => 'Brenner', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Marco', 'last' => 'Wendland', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Elke', 'last' => 'Sandner', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Frank', 'last' => 'Riedel', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Tanja', 'last' => 'Kirchner', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Hendrik', 'last' => 'Stauber', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Britta', 'last' => 'Mahler', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Kai', 'last' => 'Wernicke', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Sandra', 'last' => 'Löffler', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Torsten', 'last' => 'Achenbach', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Ines', 'last' => 'Gerlach', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Patrick', 'last' => 'Söllner', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Manuela', 'last' => 'Ebert', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Jens', 'last' => 'Kaltenbach', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Carolin', 'last' => 'Rademacher', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Matthias', 'last' => 'Nowak', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Steffi', 'last' => 'Hübner', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Alexander', 'last' => 'Perner', 'gender' => Gender::Male, 'title' => 'Dr.'],
        ['first' => 'Kerstin', 'last' => 'Baumhauer', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Robert', 'last' => 'Grimm', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Yvonne', 'last' => 'Sattler', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Norbert', 'last' => 'Feldmann', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Astrid', 'last' => 'Konrad', 'gender' => Gender::Female, 'title' => null],
        ['first' => 'Lars', 'last' => 'Bittner', 'gender' => Gender::Male, 'title' => null],
        ['first' => 'Regina', 'last' => 'Nolte', 'gender' => Gender::Female, 'title' => null],
    ];

    public function __construct(private readonly CreateContact $createContact) {}

    public function run(): void
    {
        if (Contact::query()->exists()) {
            return;
        }

        $customers = Customer::query()
            ->where('type', CustomerType::Company)
            ->orderBy('id')
            ->get();

        if ($customers->isEmpty()) {
            return;
        }

        $roles = ContactRole::query()->orderBy('sort_order')->get();

        foreach ($this->people as $index => $person) {
            $customer = $customers[$index % $customers->count()];
            $slug = Str::slug($person['first'].'.'.$person['last'], '.');
            $domain = Str::slug(Str::before($customer->short_label, ' ')).'.example.de';

            $assignments = [[
                'customer_id' => $customer->id,
                'role_ids' => [$roles[$index % $roles->count()]->id],
                'is_primary_contact' => $index % 3 === 0,
                'is_billing_contact' => $index % 5 === 0,
                'priority' => 100 - ($index % 3) * 10,
                'is_active' => true,
            ]];

            // Zwei Ansprechpartner betreuen bewusst mehrere Kunden.
            if (in_array($index, [0, 1], strict: true)) {
                $weitererKunde = $customers[($index + 7) % $customers->count()];

                $assignments[] = [
                    'customer_id' => $weitererKunde->id,
                    'role_ids' => [$roles[($index + 2) % $roles->count()]->id],
                    'is_primary_contact' => false,
                    'is_billing_contact' => true,
                    'priority' => 120,
                    'is_active' => true,
                ];
            }

            ($this->createContact)(
                attributes: [
                    'salutation' => ($person['gender'] === Gender::Male ? Salutation::Herr : Salutation::Frau)->value,
                    'academic_title' => $person['title'],
                    'first_name' => $person['first'],
                    'last_name' => $person['last'],
                    'gender' => $person['gender']->value,
                    'preferred_contact_method' => ($index % 4 === 0 ? ContactMethod::Phone : ContactMethod::Email)->value,
                ],
                assignments: $assignments,
                emails: [
                    ['email' => "{$slug}@{$domain}", 'type' => ContactChannelType::Business->value, 'is_primary' => true],
                ],
                phones: [
                    ['number' => '+49 '.(211 + $index).' '.(4000000 + $index * 1234), 'type' => ContactChannelType::Business->value, 'is_primary' => true],
                    ['number' => '+49 151 '.(5000000 + $index * 4321), 'type' => ContactChannelType::Mobile->value],
                ],
            );
        }
    }
}
