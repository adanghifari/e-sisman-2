<?php

namespace App\Actions\MasterData\DocumentType;

use App\Models\DocumentType;

class ToggleDocumentTypeStatus
{
    public function handle(DocumentType $documentType): DocumentType
    {
        $documentType->update([
            'is_active' => ! $documentType->is_active,
        ]);

        return $documentType->refresh();
    }
}
