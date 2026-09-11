<?php

namespace App\Actions\MasterData\DocumentType;

use App\Models\DocumentType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateDocumentType
{
    public function handle(DocumentType $documentType, array $data): DocumentType
    {
        $validated = Validator::make($data, [
            'nama_types' => [
                'required',
                'string',
                'max:255',
                Rule::unique('m_document_types', 'nama_types')->ignore($documentType->id),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ])->validate();

        $documentType->update([
            'nama_types' => trim($validated['nama_types']),
            'is_active' => $validated['is_active'] ?? $documentType->is_active,
        ]);

        return $documentType->refresh();
    }
}
