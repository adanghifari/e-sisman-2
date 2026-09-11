<?php

namespace App\Actions\MasterData\Department;

use App\Models\Department;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateDepartment
{
    public function handle(array $data): Department
    {
        $validated = Validator::make($data, [
            'kode_department' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'kode_department'),
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

        return Department::create([
            'kode_department' => strtoupper(trim($validated['kode_department'])),
            'nama_department' => trim($validated['nama_department']),
            'is_active' => $validated['is_active'] ?? true,
        ]);
    }
}
