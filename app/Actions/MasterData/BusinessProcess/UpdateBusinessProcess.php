<?php

namespace App\Actions\MasterData\BusinessProcess;

use App\Models\BusinessProcess;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateBusinessProcess
{
    public function handle(BusinessProcess $businessProcess, array $data): BusinessProcess
    {
        $validated = Validator::make($data, [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('m_proses_bisnis', 'kode')->ignore($businessProcess->id),
            ],
            'nama_proses_bisnis' => [
                'required',
                'string',
                'max:255',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ])->validate();

        $businessProcess->update([
            'kode' => strtoupper(trim($validated['kode'])),
            'nama_proses_bisnis' => trim($validated['nama_proses_bisnis']),
            'is_active' => $validated['is_active'] ?? $businessProcess->is_active,
        ]);

        return $businessProcess->refresh();
    }
}
