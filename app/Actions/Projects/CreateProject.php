<?php

namespace App\Actions\Projects;

use App\Enums\OperationsStatus;
use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Legt ein Projekt an und vergibt die Projektnummer.
 *
 * @phpstan-type ProjectInput array{
 *     name: string,
 *     description?: ?string,
 *     project_type_id?: ?int,
 *     responsible_user_id?: ?int,
 *     status?: ?string,
 *     start_date?: ?string,
 *     deadline?: ?string,
 *     risk_note?: ?string,
 *     backup_status?: ?string,
 *     security_status?: ?string,
 *     update_status?: ?string,
 *     operations_checked_on?: ?string,
 * }
 */
class CreateProject
{
    public function __construct(private readonly GenerateProjectNumber $generateProjectNumber) {}

    /**
     * @param  ProjectInput  $attributes
     */
    public function __invoke(Customer $customer, array $attributes): Project
    {
        return DB::transaction(function () use ($customer, $attributes): Project {
            $project = new Project;

            $project->fill([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'project_type_id' => $attributes['project_type_id'] ?? null,
                'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
                'status' => $attributes['status'] ?? ProjectStatus::Planned,
                'start_date' => $attributes['start_date'] ?? null,
                'deadline' => $attributes['deadline'] ?? null,
                'risk_note' => $attributes['risk_note'] ?? null,
                'backup_status' => $attributes['backup_status'] ?? OperationsStatus::Unknown,
                'security_status' => $attributes['security_status'] ?? OperationsStatus::Unknown,
                'update_status' => $attributes['update_status'] ?? OperationsStatus::Unknown,
                'operations_checked_on' => $attributes['operations_checked_on'] ?? null,
            ]);

            $project->customer_id = $customer->getKey();
            $project->project_number = ($this->generateProjectNumber)();
            $project->save();

            return $project;
        });
    }
}
