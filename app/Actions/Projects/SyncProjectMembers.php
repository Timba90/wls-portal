<?php

namespace App\Actions\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Setzt das Team eines Projekts auf die uebergebene Liste.
 *
 * @phpstan-type MemberInput array{user_id: int, role?: ?string}
 */
class SyncProjectMembers
{
    /**
     * @param  array<int, MemberInput>  $members
     */
    public function __invoke(Project $project, array $members): void
    {
        DB::transaction(function () use ($project, $members): void {
            $behalten = [];

            foreach (array_values($members) as $position => $mitglied) {
                $eintrag = $project->members()->updateOrCreate(
                    ['user_id' => $mitglied['user_id']],
                    ['role' => $mitglied['role'] ?? null, 'sort_order' => $position],
                );

                $behalten[] = $eintrag->getKey();
            }

            $project->members()->whereNotIn('id', $behalten)->delete();
        });
    }
}
