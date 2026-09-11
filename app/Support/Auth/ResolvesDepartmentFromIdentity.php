<?php

namespace App\Support\Auth;

use App\Models\Department;

class ResolvesDepartmentFromIdentity
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resolveId(array $attributes): ?int
    {
        $departmentId = $attributes['m_department_id'] ?? null;

        if ($departmentId !== null && Department::query()->whereKey($departmentId)->exists()) {
            return (int) $departmentId;
        }

        $departmentCode = $attributes['department_code'] ?? $attributes['kode_department'] ?? null;

        if (filled($departmentCode)) {
            $department = Department::query()
                ->where('kode_department', trim((string) $departmentCode))
                ->first();

            if ($department !== null) {
                return $department->id;
            }
        }

        $departmentName = $attributes['department_name'] ?? $attributes['nama_department'] ?? null;

        if (filled($departmentName)) {
            $department = Department::query()
                ->where('nama_department', trim((string) $departmentName))
                ->first();

            if ($department !== null) {
                return $department->id;
            }
        }

        return null;
    }
}
