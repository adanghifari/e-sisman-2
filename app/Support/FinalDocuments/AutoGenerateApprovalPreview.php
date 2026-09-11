<?php

namespace App\Support\FinalDocuments;

use App\Models\Document;
use App\Models\DocumentFinalArtifact;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoGenerateApprovalPreview
{
    public function __construct(
        private readonly FinalArtifactGenerator $finalArtifactGenerator,
        private readonly FinalDocumentArtifactGenerator $finalDocumentArtifactGenerator,
    ) {}

    public function generateIfNeeded(
        Document|int $document,
        User|int|null $generatedBy = null,
        PdfCompositionMode $mode = PdfCompositionMode::FIT_WIDTH_TO_SAFE_TOP,
    ): ?DocumentFinalArtifact {
        $documentId = $document instanceof Document ? $document->id : $document;
        $generatedByUser = $generatedBy instanceof User
            ? $generatedBy
            : ($generatedBy !== null ? User::query()->find($generatedBy) : null);
        $preparation = null;
        $existingArtifact = null;

        try {
            DB::transaction(function () use ($documentId, $generatedByUser, &$preparation, &$existingArtifact): void {
                $lockedDocument = Document::query()
                    ->whereKey($documentId)
                    ->lockForUpdate()
                    ->first();

                if ($lockedDocument === null) {
                    Log::warning('Approval preview auto-generation skipped because document was not found.', [
                        'document_id' => $documentId,
                    ]);

                    return;
                }

                $lockedDocument->loadMissing(['status', 'documentLevel', 'files']);

                if ($lockedDocument->status?->nama_status !== StatusDocument::PROPOSED) {
                    Log::info('Approval preview auto-generation skipped because document is not proposed.', [
                        'document_id' => $lockedDocument->id,
                        'status' => $lockedDocument->status?->nama_status,
                    ]);

                    return;
                }

                if ($lockedDocument->request_type === 'obsolete') {
                    Log::info('Approval preview auto-generation skipped for obsolete request.', [
                        'document_id' => $lockedDocument->id,
                    ]);

                    return;
                }

                $sourceFile = $this->finalArtifactGenerator->resolveSourceDocumentFile($lockedDocument);
                $existingArtifact = DocumentFinalArtifact::query()
                    ->where('t_document_id', $lockedDocument->id)
                    ->where('source_document_file_id', $sourceFile->id)
                    ->where('artifact_type', DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW)
                    ->whereIn('generation_status', [
                        DocumentFinalArtifact::STATUS_PENDING,
                        DocumentFinalArtifact::STATUS_PROCESSING,
                        DocumentFinalArtifact::STATUS_GENERATED,
                    ])
                    ->lockForUpdate()
                    ->latest('generation_number')
                    ->first();

                if ($existingArtifact !== null) {
                    Log::info('Approval preview auto-generation skipped because artifact already exists for source.', [
                        'document_id' => $lockedDocument->id,
                        'artifact_id' => $existingArtifact->id,
                        'generation_number' => $existingArtifact->generation_number,
                        'generation_status' => $existingArtifact->generation_status,
                    ]);

                    return;
                }

                $preparation = $this->finalArtifactGenerator->prepareApprovalPreview($lockedDocument, $generatedByUser);

                Log::info('Approval preview auto-generation claimed artifact.', [
                    'document_id' => $lockedDocument->id,
                    'artifact_id' => $preparation->artifact->id,
                    'generation_number' => $preparation->artifact->generation_number,
                ]);
            });

            if ($preparation === null) {
                return $existingArtifact;
            }

            $artifact = $this->finalDocumentArtifactGenerator->generatePrepared(
                $preparation,
                $mode,
                PdfDocumentContext::APPROVAL_PREVIEW,
            );

            Log::info('Approval preview auto-generation completed.', [
                'document_id' => $artifact->t_document_id,
                'artifact_id' => $artifact->id,
                'generation_number' => $artifact->generation_number,
            ]);

            return $artifact;
        } catch (Throwable $exception) {
            Log::error('Approval preview auto-generation failed.', [
                'document_id' => $documentId,
                'artifact_id' => $preparation?->artifact->id,
                'generation_number' => $preparation?->artifact->generation_number,
                'error' => $exception->getMessage(),
            ]);

            return $preparation?->artifact->refresh();
        }
    }
}
