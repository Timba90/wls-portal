<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Auftragsbestätigung', 'Vertrag', 'Leistungsbeschreibung', 'Protokoll',
            ]),
            'description' => null,
        ];
    }
}
