<?php

namespace App\Actions\MasterData\Department;

use App\Models\Department;

class ToggleDepartmentStatus
{
    public function handle(Department $department): Department
    {
        $department->update([
            'is_active' => ! $department->is_active,
        ]);

        return $department->refresh();
    }
}
