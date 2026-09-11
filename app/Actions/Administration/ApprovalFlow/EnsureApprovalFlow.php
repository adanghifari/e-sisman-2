<?php

namespace App\Actions\Administration\ApprovalFlow;

use App\Models\ApprovalFlow;
use App\Models\DocumentLevel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EnsureApprovalFlow
{
    public function handle(array $data): ApprovalFlow
    {
        $validated = Validator::make($data, [
            'm_document_level_id' => [
                'required',
                'integer',
                Rule::exists('m_document_levels', 'id'),
            ],
            'nama_flow' => [
                'nullable',
                'string',
                'max:255',
            ],
        ])->validate();

        $documentLevel = DocumentLevel::findOrFail($validated['m_document_level_id']);
        $flowName = trim($validated['nama_flow'] ?? '') ?: 'Flow '.$documentLevel->nama_level;

        return ApprovalFlow::firstOrCreate(
            ['m_document_level_id' => $documentLevel->id],
            ['nama_flow' => $flowName],
        );
    }
}
