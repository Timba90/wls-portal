<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Interne Benutzer für die Entwicklung.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Martin Hoffmann', 'email' => 'martin.hoffmann@wls.test'],
            ['name' => 'Sabine Wagner', 'email' => 'sabine.wagner@wls.test'],
            ['name' => 'Katrin Berger', 'email' => 'katrin.berger@wls.test'],
        ])->each(fn (array $attributes) => User::query()->firstOrCreate(
            ['email' => $attributes['email']],
            [
                'name' => $attributes['name'],
                'password' => 'EntwicklungPasswort1!',
                'email_verified_at' => now(),
            ],
        ));
    }
}
