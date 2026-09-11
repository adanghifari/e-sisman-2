<?php

namespace App\Actions\MasterData\DocumentType;

use App\Models\DocumentType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateDocumentType
{
    public function handle(array $data): DocumentType
    {
        $validated = Validator::make($data, [
            'nama_types' => [
                'required',
                'string',
                'max:255',
                Rule::unique('m_document_types', 'nama_types'),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ])->validate();

        return DocumentType::create([
            'nama_types' => trim($validated['nama_types']),
            'is_active' => $validated['is_active'] ?? true,
        ]);
    }
}
