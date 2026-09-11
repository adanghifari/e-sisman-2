<?php

namespace App\Actions\MasterData\BusinessFunction;

use App\Models\BusinessFunction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateBusinessFunction
{
    public function handle(array $data): BusinessFunction
    {
        $validated = Validator::make($data, [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('m_proses_fungsi', 'kode'),
            ],
            'nama_proses_fungsi' => [
                'required',
                'string',
                'max:255',
                Rule::unique('m_proses_fungsi', 'nama_proses_fungsi'),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ])->validate();

        return BusinessFunction::create([
            'kode' => strtoupper(trim($validated['kode'])),
            'nama_proses_fungsi' => trim($validated['nama_proses_fungsi']),
            'is_active' => $validated['is_active'] ?? true,
        ]);
    }
}
