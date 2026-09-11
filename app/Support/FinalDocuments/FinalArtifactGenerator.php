<?php

namespace App\Support\FinalDocuments;

use App\Models\Approval;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentFinalArtifact;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FinalArtifactGenerator
{
    private const OFFICIAL_PREPARER_SIGNATURE_STAGE = 'TTD Penyusun Resmi';

    /**
     * Prepare a final artifact record and normalized renderer payload.
     *
     * This method intentionally does not render, merge, stamp, or write a PDF.
     */
    public function prepare(
        Document $document,
        ?User $generatedBy = null,
        string $artifactType = DocumentFinalArtifact::TYPE_FINAL_DOCUMENT,
    ): FinalArtifactPreparation {
        $document->loadMissing([
            'status',
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'departments',
            'officialPreparer.department',
            'files',
            'approvals.status',
        ]);

        $this->assertEligible($document);

        $sourceFile = $this->resolveSourceDocumentFile($document);

        if (! Storage::disk('local')->exists($sourceFile->path_file)) {
            throw new RuntimeException('Source document file is missing from storage.');
        }

        $payload = $this->buildPayload($document, $sourceFile);
        $artifact = $this->createPendingArtifact($document, $sourceFile, $generatedBy, $artifactType);

        return new FinalArtifactPreparation($artifact, $payload);
    }

    public function prepareApprovalPreview(Document $document, ?User $generatedBy = null): FinalArtifactPreparation
    {
        $document->loadMissing([
            'status',
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'departments',
            'officialPreparer.department',
            'files',
            'approvals.status',
        ]);

        $this->assertApprovalPreviewEligible($document);

        $sourceFile = $this->resolveSourceDocumentFile($document);

        if (! Storage::disk('local')->exists($sourceFile->path_file)) {
            throw new RuntimeException('Source document file is missing from storage.');
        }

        $payload = $this->buildPayload($document, $sourceFile);
        $payload['document']['published_at'] = null;
        $payload['document']['approved_at'] = null;
        $artifact = $this->createPendingArtifact(
            $document,
            $sourceFile,
            $generatedBy,
            DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW,
        );

        return new FinalArtifactPreparation($artifact, $payload);
    }

    public function assertEligible(Document $document): void
    {
        $document->loadMissing('status');

        if (! in_array($document->status?->nama_status, [StatusDocument::APPROVED, StatusDocument::OBSOLETE], true)) {
            throw new DomainException('Only approved or obsolete documents can be prepared as final artifacts.');
        }

        if ($document->request_type === 'obsolete') {
            throw new DomainException('Obsolete request documents cannot be prepared as final artifacts.');
        }
    }

    public function assertApprovalPreviewEligible(Document $document): void
    {
        $document->loadMissing('status');

        if ($document->status?->nama_status !== StatusDocument::PROPOSED) {
            throw new DomainException('Only proposed documents can be prepared as approval preview artifacts.');
        }

        if ($document->request_type === 'obsolete') {
            throw new DomainException('Obsolete request documents cannot be prepared as approval preview artifacts.');
        }
    }

    public function resolveSourceDocumentFile(Document $document): DocumentFile
    {
        $document->loadMissing('files');

        $preferredTypes = $document->request_type === 'revision'
            ? ['revision_content']
            : match ($document->documentLevel?->kode) {
                'level-1' => ['imported_document', 'filled_template'],
                default => ['filled_template', 'imported_document'],
            };

        $sourceFile = $document->files
            ->whereIn('type_file', $preferredTypes)
            ->sortBy(fn (DocumentFile $file): int => array_search($file->type_file, $preferredTypes, true))
            ->first();

        if ($sourceFile === null) {
            throw new DomainException('No main source document file is available for final artifact preparation.');
        }

        return $sourceFile;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Document $document, DocumentFile $sourceFile): array
    {
        $document->loadMissing([
            'status',
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'departments',
            'officialPreparer.department',
            'files',
            'approvals.status',
            'revisedFrom.documentLevel',
            'revisedFrom.documentType',
        ]);
        $coverDocumentLevel = $this->coverDocumentLevel($document);
        $coverDocumentType = $this->coverDocumentType($document);

        return [
            'document' => [
                'id' => $document->id,
                'name' => $document->nama_dokumen,
                'number' => $document->nomor_dokumen,
                'revision' => $document->nomor_revisi,
                'revision_label' => $document->formatted_revision,
                'revision_form_number' => $document->nomor_lembar_revisi,
                'published_at' => $document->tanggal_terbit,
                'approved_at' => $document->approved_at,
                'type' => $coverDocumentType?->nama_types,
                'level' => [
                    'id' => $coverDocumentLevel?->id,
                    'code' => $coverDocumentLevel?->kode,
                    'name' => $coverDocumentLevel?->nama_level,
                    'document_name' => $coverDocumentLevel?->nama_dokumen,
                ],
                'business_process' => $document->businessProcess?->nama_proses_bisnis,
                'business_function' => $document->businessFunction?->nama_proses_fungsi,
                'departments' => $document->departments
                    ->map(fn ($department): array => [
                        'id' => $department->id,
                        'name' => $department->nama_department,
                        'code' => $department->kode_department,
                    ])
                    ->values()
                    ->all(),
            ],
            'preparers' => $this->collectPreparers($document),
            'approvals' => $this->collectApprovals($document),
            'revision_approvals' => $this->collectRevisionApprovals($document),
            'source' => [
                'id' => $sourceFile->id,
                'type' => $sourceFile->type_file,
                'document_number' => $sourceFile->document_number,
                'path_file' => $sourceFile->path_file,
                'original_file_name' => $sourceFile->original_file_name,
                'stored_file_name' => $sourceFile->stored_file_name,
                'file_size' => $sourceFile->file_size,
            ],
            'attachments' => $this->collectAttachments($document),
        ];
    }

    private function coverDocumentLevel(Document $document): ?DocumentLevel
    {
        if ($document->request_type === 'revision' && $document->documentLevel?->kode === 'level-4') {
            return $document->revisedFrom?->documentLevel
                ?: $document->documentLevel;
        }

        return $document->documentLevel;
    }

    private function coverDocumentType(Document $document): ?DocumentType
    {
        if ($document->request_type === 'revision' && $document->documentLevel?->kode === 'level-4') {
            return $document->revisedFrom?->documentType
                ?: $document->documentType;
        }

        return $document->documentType;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectAttachments(Document $document): array
    {
        $attachments = $document->files()
            ->where('type_file', 'attachment')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (DocumentFile $file): string => $file->attachmentSortKey());

        if ($document->request_type === 'revision') {
            $revisionForm = $document->files()
                ->where('type_file', 'revision_form')
                ->latest('id')
                ->first();

            if ($revisionForm !== null) {
                $attachments->prepend($revisionForm);
            }
        }

        return $attachments
            ->values()
            ->map(fn (DocumentFile $file, int $index): array => [
                'id' => $file->id,
                'number' => $index + 1,
                'document_number' => $file->document_number,
                'title' => $file->type_file === 'revision_form'
                    ? ($file->attachment_title ?: 'Lembar Revisi')
                    : ($file->attachment_title ?: $file->original_file_name),
                'type' => $file->type_file,
                'order' => $file->attachment_order,
                'path_file' => $file->path_file,
                'original_file_name' => $file->original_file_name,
                'stored_file_name' => $file->stored_file_name,
                'file_size' => $file->file_size,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectPreparers(Document $document): array
    {
        $document->loadMissing('officialPreparer.department');

        if ($document->officialPreparer === null) {
            return [];
        }

        return [[
            'id' => $document->officialPreparer->id,
            'name' => $document->official_preparer_name_snapshot ?: $document->officialPreparer->name,
            'position' => $document->official_preparer_position_snapshot ?: $document->officialPreparer->jabatan,
            'department' => $document->official_preparer_department_snapshot ?: $document->officialPreparer->department?->nama_department,
            'department_code' => null,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectApprovals(Document $document): array
    {
        $document->loadMissing('approvals.status');

        return $document->approvals
            ->filter(fn (Approval $approval): bool => $approval->responded_at !== null
                && ! $this->isOfficialPreparerSignatureStage($approval))
            ->map(fn (Approval $approval): array => [
                'id' => $approval->id,
                'stage_name' => $approval->stage_name_snapshot ?? $approval->stages,
                'stage_order' => $approval->stage_order_snapshot,
                'sort_order' => $approval->stage_order_snapshot ?? $this->stageOrderFromCurrentFlow($document, $approval),
                'approver' => [
                    'approval_id' => $approval->id,
                    'name' => $approval->approver_name_snapshot,
                    'position' => $approval->approver_position_snapshot,
                    'department' => $approval->approver_department_snapshot,
                    'responded_at' => $approval->responded_at,
                ],
            ])
            ->groupBy(fn (array $approval): string => ($approval['sort_order'] ?? 'unknown').'|'.($approval['stage_name'] ?? ''))
            ->map(function (Collection $stageApprovals): array {
                $firstApproval = $stageApprovals->first();

                return [
                    'stage_name' => $firstApproval['stage_name'],
                    'stage_order' => $firstApproval['stage_order'],
                    'approvers' => $stageApprovals
                        ->map(fn (array $approval): array => [
                            ...$approval['approver'],
                            'approval_id' => $approval['id'],
                        ])
                        ->values()
                        ->all(),
                    'sort_order' => $firstApproval['sort_order'],
                ];
            })
            ->sortBy(fn (array $stage): string => sprintf(
                '%010d-%s',
                $stage['sort_order'] ?? PHP_INT_MAX,
                $stage['stage_name'] ?? '',
            ))
            ->map(fn (array $stage): array => [
                'stage_name' => $stage['stage_name'],
                'stage_order' => $stage['stage_order'],
                'approvers' => $stage['approvers'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectRevisionApprovals(Document $document): array
    {
        if ($document->request_type !== 'revision') {
            return [];
        }

        if (! in_array($document->status?->nama_status, [StatusDocument::APPROVED, StatusDocument::OBSOLETE], true)) {
            return [];
        }

        return $this->collectApprovals($document);
    }

    private function isOfficialPreparerSignatureStage(Approval $approval): bool
    {
        return collect([
            $approval->stages,
            $approval->stage_name_snapshot,
        ])
            ->filter()
            ->contains(fn (string $stage): bool => trim($stage) === self::OFFICIAL_PREPARER_SIGNATURE_STAGE);
    }

    private function createPendingArtifact(
        Document $document,
        DocumentFile $sourceFile,
        ?User $generatedBy,
        string $artifactType,
    ): DocumentFinalArtifact {
        return DB::transaction(function () use ($document, $sourceFile, $generatedBy, $artifactType): DocumentFinalArtifact {
            $generationNumber = ((int) DocumentFinalArtifact::query()
                ->where('t_document_id', $document->id)
                ->where('artifact_type', $artifactType)
                ->lockForUpdate()
                ->max('generation_number')) + 1;
            $fileName = $this->generatedFileName($document, $generationNumber, $artifactType);

            return DocumentFinalArtifact::query()->create([
                't_document_id' => $document->id,
                'source_document_file_id' => $sourceFile->id,
                'artifact_type' => $artifactType,
                'generation_number' => $generationNumber,
                'generation_status' => DocumentFinalArtifact::STATUS_PENDING,
                'path_file' => "documents/final/{$document->id}/{$artifactType}/{$generationNumber}/{$fileName}",
                'generated_file_name' => $fileName,
                'generated_by' => $generatedBy?->id,
            ]);
        });
    }

    private function generatedFileName(Document $document, int $generationNumber, string $artifactType): string
    {
        $baseName = Str::slug($document->nomor_dokumen ?: $document->nama_dokumen) ?: 'document';
        $prefix = $artifactType === DocumentFinalArtifact::TYPE_APPROVAL_SHEET
            ? 'approval-sheet'
            : ($artifactType === DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW
                ? 'approval-preview'
                : 'final');

        return "{$prefix}-{$baseName}-g{$generationNumber}.pdf";
    }

    private function stageOrderFromCurrentFlow(Document $document, Approval $approval): ?int
    {
        $document->loadMissing([
            'documentLevel.approvalFlows.stages',
            'revisedFrom.documentLevel.approvalFlows.stages',
        ]);

        $documentLevel = $document->documentLevel?->kode === 'level-4'
            ? ($document->revisedFrom?->documentLevel ?: $document->documentLevel)
            : $document->documentLevel;

        return $documentLevel
            ?->approvalFlows
            ->flatMap(fn ($flow) => $flow->stages)
            ->first(fn ($stage): bool => ($stage->display_label ?: 'Approval') === $approval->stages)
            ?->stage_order;
    }
}
