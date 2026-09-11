<?php

namespace App\Support\FinalDocuments;

use App\Models\Document;

enum PdfDocumentContext: string
{
    case APPROVAL_PREVIEW = 'approval_preview';
    case FINAL_DOCUMENT = 'final_document';
    case FINAL_DOCUMENT_WITHOUT_APPROVAL_SHEET = 'final_document_without_approval_sheet';

    public static function finalFor(Document $document): self
    {
        $document->loadMissing('documentLevel');

        return $document->documentLevel?->kode === 'level-1'
            ? self::FINAL_DOCUMENT_WITHOUT_APPROVAL_SHEET
            : self::FINAL_DOCUMENT;
    }

    public function includesApprovalSheet(): bool
    {
        return $this === self::FINAL_DOCUMENT;
    }
}
