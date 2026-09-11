<?php

namespace App\Support\FinalDocuments;

use App\Models\Document;
use App\Models\DocumentFinalArtifact;
use App\Models\User;

class ApprovalPreviewArtifactGenerator
{
    public function __construct(
        private readonly FinalArtifactGenerator $finalArtifactGenerator,
        private readonly FinalDocumentArtifactGenerator $finalDocumentArtifactGenerator,
    ) {}

    public function generate(
        Document $document,
        ?User $generatedBy = null,
        PdfCompositionMode $mode = PdfCompositionMode::FIT_WIDTH_TO_SAFE_TOP,
    ): DocumentFinalArtifact {
        return $this->finalDocumentArtifactGenerator->generatePrepared(
            $this->finalArtifactGenerator->prepareApprovalPreview($document, $generatedBy),
            $mode,
            PdfDocumentContext::APPROVAL_PREVIEW,
        );
    }
}
