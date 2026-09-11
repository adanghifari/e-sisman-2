<?php

namespace App\Support\FinalDocuments;

use App\Models\Document;
use App\Models\DocumentFinalArtifact;
use Illuminate\Support\Facades\Storage;

class DynamicFinalDocumentRenderer
{
    public function __construct(
        private readonly FinalArtifactGenerator $finalArtifactGenerator,
        private readonly CoverPdfRenderer $coverPdfRenderer,
        private readonly ApprovalSheetPdfRenderer $approvalSheetPdfRenderer,
        private readonly FinalPdfComposer $finalPdfComposer,
    ) {}

    public function render(
        Document $document,
        PdfDocumentContext $context,
        PdfCompositionMode $mode = PdfCompositionMode::FIT_WIDTH_TO_SAFE_TOP,
        ?array $watermarkStamp = null,
    ): string {
        $this->assertEligible($document, $context);

        $sourceFile = $this->finalArtifactGenerator->resolveSourceDocumentFile($document);
        $sourcePath = Storage::disk('local')->path($sourceFile->path_file);

        $payload = $this->finalArtifactGenerator->buildPayload($document, $sourceFile);

        if ($context === PdfDocumentContext::APPROVAL_PREVIEW) {
            $payload['document']['published_at'] = null;
            $payload['document']['approved_at'] = null;
        }

        if ($watermarkStamp !== null) {
            $payload['watermark_stamp'] = $watermarkStamp;
        }

        return $this->finalPdfComposer->compose(
            payload: $payload,
            coverPdf: $this->coverPdfRenderer->render($payload),
            approvalSheetPdf: $context->includesApprovalSheet()
                ? $this->approvalSheetPdfRenderer->render($payload)
                : null,
            bodyPdfPath: $sourcePath,
            mode: $mode,
            context: $context,
        )->pdf;
    }

    public function fileName(Document $document, PdfDocumentContext $context): string
    {
        $baseName = str($document->nomor_dokumen ?: $document->nama_dokumen)->slug()->value() ?: 'document';
        $prefix = $context === PdfDocumentContext::APPROVAL_PREVIEW
            ? DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW
            : DocumentFinalArtifact::TYPE_FINAL_DOCUMENT;

        return "{$prefix}-{$baseName}-dynamic.pdf";
    }

    public function canRender(Document $document, PdfDocumentContext $context): bool
    {
        try {
            $this->assertEligible($document, $context);
            $sourceFile = $this->finalArtifactGenerator->resolveSourceDocumentFile($document);

            return Storage::disk('local')->exists($sourceFile->path_file);
        } catch (\Throwable) {
            return false;
        }
    }

    private function assertEligible(Document $document, PdfDocumentContext $context): void
    {
        if ($context === PdfDocumentContext::APPROVAL_PREVIEW) {
            $this->finalArtifactGenerator->assertApprovalPreviewEligible($document);

            return;
        }

        $this->finalArtifactGenerator->assertEligible($document);
    }
}
