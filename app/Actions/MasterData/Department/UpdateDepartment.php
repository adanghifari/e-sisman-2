<?php

namespace App\Actions\MasterData\Department;

use App\Models\Department;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateDepartment
{
    public function handle(Department $department, array $data): Department
    {
        $validated = Validator::make($data, [
            'kode_department' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'kode_department')->ignore($department->id),
            ],
            'nama_department' => [
                'required',
                'string',
                'max:255',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ])->validate();

        $department->update([
            'kode_department' => strtoupper(trim($validated['kode_department'])),
            'nama_department' => trim($validated['nama_department']),
            'is_active' => $validated['is_active'] ?? $department->is_active,
        ]);

        return $department->refresh();
    }
}
