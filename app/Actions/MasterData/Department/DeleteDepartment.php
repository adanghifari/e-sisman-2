<?php

namespace App\Actions\MasterData\Department;

use App\Models\Department;
use Illuminate\Validation\ValidationException;

class DeleteDepartment
{
    /**
     * @throws ValidationException
     */
    public function handle(Department $department): void
    {
        if ($department->users()->exists() || $department->documents()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Department tidak bisa dihapus karena sudah digunakan pada user atau dokumen.',
            ]);
        }

        $department->delete();
    }
}
