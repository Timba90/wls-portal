<?php

namespace Database\Factories;

use App\Enums\NoteCategory;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => NoteCategory::General,
            'body' => $this->faker->randomElement([
                'Kunde wünscht Rückruf zur Vertragsverlängerung.',
                'Zugangsdaten wurden per verschlüsselter Nachricht übermittelt.',
                'Rechnung wurde auf Wunsch quartalsweise umgestellt.',
                'Wartungsfenster mit der IT-Leitung abgestimmt.',
            ]),
        ];
    }
}
