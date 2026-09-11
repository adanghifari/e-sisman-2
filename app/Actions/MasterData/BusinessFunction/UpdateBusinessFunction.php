<?php

namespace App\Actions\MasterData\BusinessFunction;

use App\Models\BusinessFunction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateBusinessFunction
{
    public function handle(BusinessFunction $businessFunction, array $data): BusinessFunction
    {
        $validated = Validator::make($data, [
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('m_proses_fungsi', 'kode')->ignore($businessFunction->id),
            ],
            'nama_proses_fungsi' => [
                'required',
                'string',
                'max:255',
                Rule::unique('m_proses_fungsi', 'nama_proses_fungsi')->ignore($businessFunction->id),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ])->validate();

        $businessFunction->update([
            'kode' => strtoupper(trim($validated['kode'])),
            'nama_proses_fungsi' => trim($validated['nama_proses_fungsi']),
            'is_active' => $validated['is_active'] ?? $businessFunction->is_active,
        ]);

        return $businessFunction->refresh();
    }
}
