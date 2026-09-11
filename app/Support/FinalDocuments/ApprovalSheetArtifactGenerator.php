<?php

namespace App\Support\FinalDocuments;

use App\Models\Document;
use App\Models\DocumentFinalArtifact;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ApprovalSheetArtifactGenerator
{
    public function __construct(
        private readonly FinalArtifactGenerator $finalArtifactGenerator,
        private readonly ApprovalSheetPdfRenderer $approvalSheetPdfRenderer,
    ) {}

    public function generate(Document $document, ?User $generatedBy = null): DocumentFinalArtifact
    {
        $preparation = $this->finalArtifactGenerator->prepare(
            $document,
            $generatedBy,
            DocumentFinalArtifact::TYPE_APPROVAL_SHEET,
        );
        $artifact = $preparation->artifact;

        $artifact->update([
            'generation_status' => DocumentFinalArtifact::STATUS_PROCESSING,
            'generation_error' => null,
        ]);

        try {
            $pdf = $this->approvalSheetPdfRenderer->render($preparation->payload);

            if (! Storage::disk('local')->put($artifact->path_file, $pdf)) {
                throw new RuntimeException('Approval sheet PDF could not be written to storage.');
            }

            $artifact->update([
                'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
                'checksum_sha256' => hash('sha256', $pdf),
                'file_size' => strlen($pdf),
                'generated_at' => now(),
                'generation_error' => null,
            ]);

            return $artifact->refresh();
        } catch (Throwable $exception) {
            $artifact->update([
                'generation_status' => DocumentFinalArtifact::STATUS_FAILED,
                'generation_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
