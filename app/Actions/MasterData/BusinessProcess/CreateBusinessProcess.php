<?php

namespace App\Actions\MasterData\BusinessProcess;

use App\Models\BusinessProcess;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateBusinessProcess
{
    public function handle(array $data): BusinessProcess
    {
        $validated = Validator::make($data, [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('m_proses_bisnis', 'kode'),
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

        return BusinessProcess::create([
            'kode' => strtoupper(trim($validated['kode'])),
            'nama_proses_bisnis' => trim($validated['nama_proses_bisnis']),
            'is_active' => $validated['is_active'] ?? true,
        ]);
    }
}
