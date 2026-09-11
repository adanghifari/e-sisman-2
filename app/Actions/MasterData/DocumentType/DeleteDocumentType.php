<?php

namespace App\Actions\MasterData\DocumentType;

use App\Models\DocumentType;
use Illuminate\Validation\ValidationException;

class DeleteDocumentType
{
    /**
     * @throws ValidationException
     */
    public function handle(DocumentType $documentType): void
    {
        if ($documentType->documents()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Jenis dokumen tidak bisa dihapus karena sudah digunakan pada dokumen.',
            ]);
        }

        $documentType->delete();
    }
}
