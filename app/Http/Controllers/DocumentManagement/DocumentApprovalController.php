<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\ApprovalFlowStage;
use App\Models\ApprovalStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentFinalArtifact;
use App\Models\DocumentLevel;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use App\Support\DocumentFiles\DocumentFileNumbering;
use App\Support\DocumentHistory;
use App\Support\DocumentRejectionHistory;
use App\Support\FinalDocuments\AutoGenerateApprovalPreview;
use App\Support\FinalDocuments\AutoGenerateFinalDocument;
use App\Support\FinalDocuments\DynamicFinalDocumentRenderer;
use App\Support\FinalDocuments\PdfDocumentContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DocumentApprovalController extends Controller
{
    private const OFFICIAL_PREPARER_STAGE = 'TTD Penyusun Resmi';

    public function show(Request $request, Document $document): View
    {
        $this->authorizeDocumentAccess($request, $document);

        $document->load([
            'status',
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'creator',
            'officialPreparer',
            'departments',
            'files.uploader',
            'finalArtifacts',
            'approvals.status',
            'approvals.approver',
            'approvals.role',
            'approvals.approvalFlowStage',
            'documentLevel.approvalFlows.stages',
            'revisedFrom.documentLevel.approvalFlows.stages',
            'revisedFrom.creator',
            'revisedFrom.files.uploader',
        ]);

        $this->normalizeSubmittedSourceAttachmentLineage($document);
        $document->load('files.uploader');

        $obsoleteSourceContentFiles = $document->request_type === 'obsolete'
            ? $document->revisedFrom?->files
                ->whereIn('type_file', ['filled_template', 'imported_document', 'revision_content'])
                ->values() ?? collect()
            : collect();

        return view('document-management.approval-detail', [
            'document' => $document,
            'masterDisplayNumber' => $this->masterDisplayNumber($document),
            'revisionRequestDisplayNumber' => $this->revisionRequestDisplayNumber($document),
            'activeApproval' => $this->activeApproval($request, $document),
            'approvalFlowStages' => $this->approvalFlowStages($document),
            'approvalFlowDocumentLevel' => $this->approvalFlowDocumentLevel($document),
            'canManageApproverAssignment' => $this->canManageApproverAssignment($request, $document),
            'canUpdateSubmittedDocument' => $this->canUpdateSubmittedDocument($request, $document),
            'canResubmitRejectedDocument' => $this->canResubmitRejectedDocument($request, $document),
            'assignableUsers' => User::query()->with('department')->orderBy('name')->get(),
            'departments' => Department::query()->active()->orderBy('nama_department')->get(),
            'contentFiles' => $document->files->whereIn('type_file', [
                'filled_template',
                'filled_template_word',
                'imported_document',
                'revision_content',
                'revision_content_word',
                'revision_form',
                'revision_form_word',
                'revision_before',
                'revision_after',
            ])->values(),
            'obsoleteSourceContentFiles' => $obsoleteSourceContentFiles,
            'attachmentFiles' => $document->files
                ->where('type_file', 'attachment')
                ->sortBy(fn (DocumentFile $file): string => $file->attachmentSortKey())
                ->values(),
            'generatedPrintout' => $this->latestGeneratedPrintout($document),
            'canPreviewGeneratedPrintout' => app(DynamicFinalDocumentRenderer::class)
                ->canRender($document, $document->status?->nama_status === StatusDocument::APPROVED
                    ? PdfDocumentContext::finalFor($document)
                    : PdfDocumentContext::APPROVAL_PREVIEW),
            'documentHistory' => app(DocumentHistory::class)->forDocument($document),
            'rejectionHistory' => app(DocumentRejectionHistory::class)->forDocument($document),
        ]);
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        $approval = $this->activeApproval($request, $document);
        abort_if(! $approval, 404);
        $generatedBy = $request->user();

        DB::transaction(function () use ($request, $document, $approval, $generatedBy): void {
            $approval->fill([
                'm_approval_status_id' => ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id,
                'responded_at' => now(),
                'catatan' => $request->string('catatan')->trim()->value() ?: null,
            ])->fillResponseSnapshot($this->stageOrderSnapshotForApproval($document, $approval))
                ->save();

            $finalizedDocument = $this->advanceApprovalFlow($document->refresh());

            if ($finalizedDocument !== null) {
                $this->autoGenerateFinalDocumentAfterCommit($finalizedDocument, $generatedBy);
            }
        });

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('document_success', [
                'title' => 'Dokumen Berhasil Disetujui',
                'message' => 'Approval Anda sudah tercatat pada riwayat dokumen.',
            ]);
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeDocumentAccess($request, $document);

        $validated = $request->validate([
            'catatan' => ['required', 'string', 'max:1000'],
        ]);

        $approval = $this->activeApproval($request, $document);
        abort_if(! $approval, 404);

        DB::transaction(function () use ($document, $approval, $validated): void {
            $rejectedStatus = ApprovalStatus::findByCode(ApprovalStatus::REJECTED);
            $terminatedStatus = ApprovalStatus::findByCode(ApprovalStatus::TERMINATED);

            $approval->fill([
                'm_approval_status_id' => $rejectedStatus->id,
                'responded_at' => now(),
                'catatan' => $validated['catatan'],
            ])->fillResponseSnapshot($this->stageOrderSnapshotForApproval($document, $approval))
                ->save();

            Approval::query()
                ->where('t_document_id', $document->id)
                ->whereKeyNot($approval->id)
                ->whereHas('status', fn ($query) => $query->whereIn('kode_status', [
                    ApprovalStatus::PENDING,
                    ApprovalStatus::WAITING,
                ]))
                ->update([
                    'm_approval_status_id' => $terminatedStatus->id,
                    'responded_at' => now(),
                ]);

            $document->update([
                'm_status_document_id' => StatusDocument::findByName(StatusDocument::REJECTED)->id,
                'rejected_at' => now(),
            ]);

            $this->clearRejectedInitialSubmissionFileNumbers($document);
        });

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('document_success', [
                'title' => 'Dokumen Berhasil Ditolak',
                'message' => 'Catatan penolakan Anda sudah tersimpan pada riwayat dokumen.',
            ]);
    }

    private function clearRejectedInitialSubmissionFileNumbers(Document $document): void
    {
        if ($document->revised_from !== null || $document->request_type !== null) {
            return;
        }

        $document->files()
            ->whereNotNull('document_number')
            ->update([
                'document_number' => null,
                'updated_at' => now(),
            ]);
    }

    public function assign(Request $request, Document $document): RedirectResponse
    {
        $document->refresh();

        if ($this->isDocumentAssignmentLocked($document)) {
            return $this->assignmentErrorRedirect($document, [
                'stage_approvers' => 'Transaksi approval sudah selesai sehingga approver tidak bisa diubah.',
            ]);
        }

        abort_unless($request->user()->isDeveloper() || $request->user()->canAssignDocument($document), 403);

        $stages = $this->approvalFlowStages($document);

        if ($stages->isEmpty()) {
            return $this->assignmentErrorRedirect($document, [
                'stage_approvers' => 'Belum ada aturan tahap approval.',
            ]);
        }

        $pendingStatus = ApprovalStatus::findByCode(ApprovalStatus::PENDING);
        $waitingStatus = ApprovalStatus::findByCode(ApprovalStatus::WAITING);
        $approvedStatus = ApprovalStatus::findByCode(ApprovalStatus::APPROVED);
        $activeStageOrder = $this->activeStageOrderForAssignment($document, $stages);
        $officialPreparerSignature = $this->officialPreparerSignature($document);

        foreach ($stages as $stage) {
            $userIds = $this->stageApproverIds($request, $document, $stage);

            if ($userIds->isEmpty()) {
                return $this->assignmentErrorRedirect($document, [
                    "stage_approvers.{$stage->id}" => "Approver tahap {$stage->stage_order} tidak boleh kosong.",
                ]);
            }

            if (User::query()->whereIn('id', $userIds)->count() !== $userIds->count()) {
                return $this->assignmentErrorRedirect($document, [
                    "stage_approvers.{$stage->id}" => "Approver tahap {$stage->stage_order} tidak valid.",
                ]);
            }
        }

        $approvedStageError = $this->approvedStageAssignmentError($request, $document, $stages);

        if ($approvedStageError !== null) {
            return $this->assignmentErrorRedirect($document, $approvedStageError);
        }

        if (! $this->assignmentDiffers($request, $document, $stages)) {
            return redirect()
                ->route('documents.approval.show', $document)
                ->with('status', 'Tidak ada perubahan approver.');
        }

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $stageStatus = $stage->stage_order === $activeStageOrder ? $pendingStatus : $waitingStatus;
            $userIds = $this->stageApproverIds($request, $document, $stage);

            Approval::query()
                ->where('t_document_id', $document->id)
                ->forApprovalFlowStage($stage)
                ->whereNull('responded_at')
                ->whereNotIn('user_id', $userIds)
                ->delete();

            foreach ($userIds as $userId) {
                $approval = Approval::query()->firstOrNew([
                    't_document_id' => $document->id,
                    'user_id' => $userId,
                    'm_approval_flow_stage_id' => $stage->id,
                ]);

                if ($approval->exists && $approval->responded_at !== null) {
                    continue;
                }

                $alreadySignedAsOfficialPreparer = $document->official_preparer_id === $userId
                    && $officialPreparerSignature !== null;

                $approval->fill([
                    'm_approval_status_id' => $alreadySignedAsOfficialPreparer ? $approvedStatus->id : $stageStatus->id,
                    'role_id' => null,
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'stages' => $stageLabel,
                    'responded_at' => $alreadySignedAsOfficialPreparer
                        ? ($officialPreparerSignature->responded_at ?? $officialPreparerSignature->assigned_at ?? now())
                        : null,
                    'created_at' => $approval->created_at ?? now(),
                    'catatan' => null,
                ]);

                if ($alreadySignedAsOfficialPreparer) {
                    $approval->fillResponseSnapshot((int) $stage->stage_order);
                }

                $approval->save();
            }
        }

        $this->markRevisionRequestAsAssigned($document);
        $finalizedDocument = $this->advanceApprovalFlow($document);

        if ($finalizedDocument !== null) {
            $this->autoGenerateFinalDocumentAfterCommit($finalizedDocument, $request->user());
        }

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('status', 'Approver berhasil disimpan.');
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function assignmentErrorRedirect(Document $document, array $errors): RedirectResponse
    {
        return redirect()
            ->route('documents.approval.show', $document)
            ->withErrors($errors)
            ->withInput();
    }

    public function updateSubmitted(Request $request, Document $document): RedirectResponse
    {
        $document->refresh();
        abort_unless($this->canUpdateSubmittedDocument($request, $document), 403);

        $validated = $request->validate($this->submittedDocumentUpdateRules($document));
        $this->validateSubmittedReplacementFileTypes($request, $document);

        DB::transaction(function () use ($request, $document, $validated): void {
            $document = Document::query()
                ->with(['status', 'documentLevel', 'revisedFrom.documentLevel', 'revisedFrom.files', 'files'])
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($this->canUpdateSubmittedDocument($request, $document), 403);

            $levelKey = $document->documentLevel?->kode;
            $isMetadataUpdate = ($validated['_update_scope'] ?? null) === 'metadata';

            if ($isMetadataUpdate) {
                $metadata = $this->submittedDocumentMetadata($document, $validated);

                $document->forceFill([
                    'nama_dokumen' => $metadata['nama_dokumen'],
                ])->save();

                if ($levelKey !== 'level-1') {
                    $document->departments()->sync($metadata['department_ids'] ?? []);
                }
            }

            $this->normalizeSubmittedSourceAttachmentLineage($document);
            $document->load('files');

            $includedSourceAttachmentIds = isset($validated['sync_existing_attachment_inclusion'])
                ? ($validated['included_existing_attachment_ids'] ?? [])
                : null;
            $reservedAttachmentNumbers = $this->excludedSubmittedSourceAttachmentNumbers($document, $includedSourceAttachmentIds);

            $this->removeSubmittedFiles($document, $validated['remove_existing_files'] ?? []);
            $this->syncSubmittedSourceAttachmentInclusion($document, $includedSourceAttachmentIds);
            $this->updateSubmittedAttachments($document, $validated['existing_attachment_titles'] ?? [], $validated['existing_attachment_orders'] ?? []);
            $this->replaceSubmittedContentFiles($request, $document);
            $this->replaceSubmittedAttachments($request, $document);
            $this->storeSubmittedAttachments($request, $document);

            $this->renumberSubmittedFiles($document, $reservedAttachmentNumbers);

            $this->clearApprovalPreviewArtifacts($document);

            $documentId = $document->id;
            $generatedById = $request->user()->id;

            DB::afterCommit(fn () => app(AutoGenerateApprovalPreview::class)
                ->generateIfNeeded($documentId, $generatedById));
        });

        return redirect()
            ->route('documents.approval.show', $document)
            ->with('status', 'Dokumen berhasil diperbarui.');
    }

    private function markRevisionRequestAsAssigned(Document $document): void
    {
        if ($document->request_type !== 'revision') {
            return;
        }

        if ($document->documentLevel?->kode !== 'level-4') {
            return;
        }

        $revisionTypeId = DocumentType::query()
            ->where('nama_types', 'Revisi')
            ->value('id');

        if ($revisionTypeId === null) {
            return;
        }

        $document->forceFill([
            'm_document_types_id' => $revisionTypeId,
        ])->save();
    }

    public function file(Request $request, Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeDocumentAccess($request, $document);
        abort_unless($document->status?->nama_status === StatusDocument::PROPOSED, 404);
        $this->authorizedFileDocument($document, $file);

        $sourcePath = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($sourcePath), 404);

        return response()->download($sourcePath, $file->original_file_name, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function preview(Request $request, Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeDocumentAccess($request, $document);
        abort_unless($document->status?->nama_status === StatusDocument::PROPOSED, 404);
        $this->authorizedFileDocument($document, $file);
        abort_unless(Str::of($file->original_file_name)->lower()->endsWith('.pdf'), 415);

        $sourcePath = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($sourcePath), 404);

        return response()->file($sourcePath, $this->pdfResponseHeaders($file));
    }

    private function pdfResponseHeaders(DocumentFile $file): array
    {
        return [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    public function generatedFile(
        Request $request,
        Document $document,
        DynamicFinalDocumentRenderer $renderer,
    ): Response {
        $this->authorizeDocumentAccess($request, $document);
        abort_if($document->request_type === 'obsolete', 404);

        $context = $document->status?->nama_status === StatusDocument::APPROVED
            ? PdfDocumentContext::finalFor($document)
            : PdfDocumentContext::APPROVAL_PREVIEW;

        return response($renderer->render($document, $context), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$renderer->fileName($document, $context).'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function authorizedFileDocument(Document $document, DocumentFile $file): Document
    {
        if ($file->t_document_id === $document->id) {
            return $document;
        }

        if ($document->request_type === 'obsolete' && $file->t_document_id === $document->revised_from) {
            return $document->revisedFrom ?: Document::query()->findOrFail($document->revised_from);
        }

        abort(404);
    }

    private function authorizeApprovalGeneratedArtifact(Document $document, DocumentFinalArtifact $artifact): void
    {
        abort_unless($artifact->t_document_id === $document->id, 404);
        abort_unless($artifact->generation_status === DocumentFinalArtifact::STATUS_GENERATED, 404);
        abort_unless(in_array($artifact->artifact_type, [
            DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW,
            DocumentFinalArtifact::TYPE_FINAL_DOCUMENT,
        ], true), 404);
    }

    private function latestGeneratedPrintout(Document $document): ?DocumentFinalArtifact
    {
        $preferredType = $document->status?->nama_status === StatusDocument::APPROVED
            ? DocumentFinalArtifact::TYPE_FINAL_DOCUMENT
            : DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW;

        return $document->finalArtifacts
            ->where('artifact_type', $preferredType)
            ->whereIn('generation_status', [
                DocumentFinalArtifact::STATUS_GENERATED,
                DocumentFinalArtifact::STATUS_FAILED,
            ])
            ->sortByDesc('generation_number')
            ->first();
    }

    private function authorizeDocumentAccess(Request $request, Document $document): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $document->user_id === $user->id
                || $document->official_preparer_id === $user->id
                || $user->canAssignDocument($document)
                || $this->hasAccessibleApproval($request, $document),
            403,
        );
    }

    private function hasAccessibleApproval(Request $request, Document $document): bool
    {
        return $document->approvals()
            ->where('user_id', $request->user()->id)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('responded_at')
                    ->orWhere(function ($query): void {
                        $query
                            ->whereNull('responded_at')
                            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING));
                    });
            })
            ->exists();
    }

    private function officialPreparerSignature(Document $document): ?Approval
    {
        if ($document->official_preparer_id === null) {
            return null;
        }

        return $document->approvals()
            ->where('user_id', $document->official_preparer_id)
            ->where('stages', self::OFFICIAL_PREPARER_STAGE)
            ->whereNotNull('responded_at')
            ->first();
    }

    private function activeApproval(Request $request, Document $document): ?Approval
    {
        $query = $document->approvals()
            ->with(['status', 'approver', 'role'])
            ->whereNull('responded_at')
            ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING));

        if (! $request->user()->isDeveloper()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->orderByDesc('assigned_at')->first();
    }

    private function stageOrderSnapshotForApproval(Document $document, Approval $approval): ?int
    {
        if ($approval->m_approval_flow_stage_id !== null) {
            $stage = $this->approvalFlowStages($document)
                ->firstWhere('id', $approval->m_approval_flow_stage_id);

            return $stage !== null ? (int) $stage->stage_order : null;
        }

        $stage = $this->approvalFlowStages($document)
            ->first(fn (ApprovalFlowStage $stage): bool => ($stage->display_label ?: 'Approval') === $approval->stages);

        return $stage !== null ? (int) $stage->stage_order : null;
    }

    private function approvalFlowStages(Document $document)
    {
        return $this->approvalFlowDocumentLevel($document)
            ?->approvalFlows
            ->flatMap(fn ($flow) => $flow->stages)
            ->sortBy('stage_order')
            ->values()
            ?? collect();
    }

    private function approvalFlowDocumentLevel(Document $document): ?DocumentLevel
    {
        $document->loadMissing([
            'documentLevel.approvalFlows.stages',
            'revisedFrom.documentLevel.approvalFlows.stages',
        ]);

        if ($document->documentLevel?->kode === 'level-4') {
            if ($document->revisedFrom?->documentLevel !== null) {
                return $document->revisedFrom->documentLevel;
            }
        }

        return $document->documentLevel;
    }

    private function masterDisplayNumber(Document $document): string
    {
        if ($document->revised_from === null) {
            return $document->nomor_dokumen ?: '-';
        }

        $rootDocument = Document::query()
            ->whereKey($document->revisionRootId())
            ->first();

        return $rootDocument?->nomor_dokumen
            ?: $document->revisedFrom?->nomor_dokumen
            ?: $document->nomor_dokumen
            ?: '-';
    }

    private function revisionRequestDisplayNumber(Document $document): ?string
    {
        if ($document->revised_from === null) {
            return null;
        }

        if ($document->request_type === 'revision') {
            return $document->nomor_dokumen ?: null;
        }

        $revisionRequest = Document::query()
            ->where('revised_from', $document->revised_from)
            ->where('request_type', 'revision')
            ->where('nomor_revisi', $document->nomor_revisi)
            ->latest('id')
            ->first();

        if ($revisionRequest?->nomor_dokumen) {
            return $revisionRequest->nomor_dokumen;
        }

        $masterDisplayNumber = $this->masterDisplayNumber($document);

        return $document->nomor_dokumen !== $masterDisplayNumber
            ? $document->nomor_dokumen
            : null;
    }

    private function isDocumentAssignmentLocked(Document $document): bool
    {
        $lockedStatuses = [StatusDocument::APPROVED, StatusDocument::REJECTED];

        if ($document->relationLoaded('status')) {
            return in_array($document->status?->nama_status, $lockedStatuses, true);
        }

        return $document->status()
            ->whereIn('nama_status', $lockedStatuses)
            ->exists();
    }

    private function canManageApproverAssignment(Request $request, Document $document): bool
    {
        if ($this->isDocumentAssignmentLocked($document)) {
            return false;
        }

        return $request->user()->isDeveloper() || $request->user()->canAssignDocument($document);
    }

    private function canUpdateSubmittedDocument(Request $request, Document $document): bool
    {
        $document->loadMissing('status');

        if ($document->request_type === 'obsolete') {
            return false;
        }

        if ($document->status?->nama_status !== StatusDocument::PROPOSED) {
            return false;
        }

        if ($this->hasAssignedApprovalFlowApprovers($document)) {
            return false;
        }

        return $request->user()->isDeveloper() || $request->user()->canUpdateSubmittedDocument($document);
    }

    private function canResubmitRejectedDocument(Request $request, Document $document): bool
    {
        return $document->status?->nama_status === StatusDocument::REJECTED
            && in_array($request->user()->id, array_filter([
                $document->user_id,
                $document->official_preparer_id,
            ]), true);
    }

    private function hasAssignedApprovalFlowApprovers(Document $document): bool
    {
        return $document->approvals()
            ->where(function ($query): void {
                $query
                    ->where('stages', '!=', self::OFFICIAL_PREPARER_STAGE)
                    ->orWhereNull('stages');
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function submittedDocumentUpdateRules(Document $document): array
    {
        $document->loadMissing('documentLevel');
        $levelKey = $document->documentLevel?->kode;
        $isMetadataUpdate = request('_update_scope') === 'metadata';
        $metadataPresence = $isMetadataUpdate ? 'required' : 'nullable';
        $rules = [
            '_update_scope' => ['nullable', Rule::in(['metadata', 'files'])],
            'nama_dokumen' => [$metadataPresence, 'string', 'max:255'],
            'tanggal_terbit' => ['nullable', 'date'],
            'nomor_dokumen_suffix' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/'],
            'replacement_files' => ['nullable', 'array'],
            'replacement_files.*' => ['file', 'mimes:pdf,doc,docx', 'max:10240'],
            'replacement_attachments' => ['nullable', 'array'],
            'replacement_attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
            'attachment_titles' => ['nullable', 'array', 'max:10'],
            'attachment_titles.*' => ['required_with:attachments.*', 'string', 'max:255'],
            'attachment_orders' => ['nullable', 'array', 'max:10'],
            'attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:50'],
            'remove_existing_files' => ['nullable', 'array'],
            'remove_existing_files.*' => ['integer', Rule::exists('t_document_files', 'id')],
            'sync_existing_attachment_inclusion' => ['nullable', 'boolean'],
            'included_existing_attachment_ids' => ['nullable', 'array'],
            'included_existing_attachment_ids.*' => ['integer', Rule::exists('t_document_files', 'id')],
            'existing_attachment_titles' => ['nullable', 'array'],
            'existing_attachment_titles.*' => ['nullable', 'string', 'max:255'],
            'existing_attachment_orders' => ['nullable', 'array'],
            'existing_attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];

        if ($levelKey !== 'level-1') {
            $rules += [
                'department_ids' => [$metadataPresence, 'array', 'min:1'],
                'department_ids.*' => [$metadataPresence, 'integer', Rule::exists('departments', 'id')],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function submittedDocumentMetadata(Document $document, array $validated): array
    {
        $document->loadMissing(['departments', 'outgoingRelations']);
        $levelKey = $document->documentLevel?->kode;

        return [
            'nama_dokumen' => $validated['nama_dokumen'] ?? $document->nama_dokumen,
            'department_ids' => $levelKey === 'level-1'
                ? $document->departments->pluck('id')->all()
                : ($validated['department_ids'] ?? $document->departments->pluck('id')->all()),
        ];
    }

    private function replaceSubmittedContentFiles(Request $request, Document $document): void
    {
        foreach ($request->file('replacement_files', []) as $fileId => $uploadedFile) {
            $file = $document->files()
                ->whereKey($fileId)
                ->whereIn('type_file', ['filled_template', 'filled_template_word', 'imported_document', 'revision_content', 'revision_content_word', 'revision_form', 'revision_form_word'])
                ->first();

            if ($file === null) {
                continue;
            }

            $type = $file->type_file;
            $this->replaceSubmittedFileRecord($file, $uploadedFile, $type, $request->user()->id);
        }
    }

    private function validateSubmittedReplacementFileTypes(Request $request, Document $document): void
    {
        $replacementTypes = collect();

        foreach ($request->file('replacement_files', []) as $fileId => $uploadedFile) {
            $file = $document->files()
                ->whereKey($fileId)
                ->whereIn('type_file', ['filled_template', 'filled_template_word', 'imported_document', 'revision_content', 'revision_content_word', 'revision_form', 'revision_form_word'])
                ->first();

            if ($file === null) {
                continue;
            }

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            $isWordFile = in_array($file->type_file, ['filled_template_word', 'revision_content_word', 'revision_form_word'], true);
            $isValid = $isWordFile
                ? in_array($extension, ['doc', 'docx'], true)
                : $extension === 'pdf';

            if (! $isValid) {
                throw ValidationException::withMessages([
                    "replacement_files.{$fileId}" => $isWordFile
                        ? 'File Word harus berformat DOC atau DOCX.'
                        : 'File dokumen harus berformat PDF.',
                ]);
            }

            $replacementTypes->put($file->type_file, (string) $fileId);
        }

        $pairedTypes = [
            ['filled_template', 'filled_template_word', 'Jika memperbarui template PDF atau Word, kedua file PDF dan Word wajib diperbarui bersama.'],
            ['revision_content', 'revision_content_word', 'Jika memperbarui dokumen revisi PDF atau Word, kedua file PDF dan Word wajib diperbarui bersama.'],
            ['revision_form', 'revision_form_word', 'Jika memperbarui lembar revisi PDF atau Word, kedua file PDF dan Word wajib diperbarui bersama.'],
        ];

        $pairedFiles = $document->files()
            ->whereIn('type_file', collect($pairedTypes)->flatMap(fn (array $pair): array => [$pair[0], $pair[1]])->all())
            ->latest('id')
            ->get()
            ->unique('type_file')
            ->keyBy('type_file');

        foreach ($pairedTypes as [$pdfType, $wordType, $message]) {
            if (! $pairedFiles->has($pdfType) || ! $pairedFiles->has($wordType)) {
                continue;
            }

            $hasPdfReplacement = $replacementTypes->has($pdfType);
            $hasWordReplacement = $replacementTypes->has($wordType);

            if (($hasPdfReplacement || $hasWordReplacement) && ! ($hasPdfReplacement && $hasWordReplacement)) {
                $missingFileId = $pairedFiles->get($hasPdfReplacement ? $wordType : $pdfType)->id;

                throw ValidationException::withMessages([
                    "replacement_files.{$missingFileId}" => $message,
                ]);
            }
        }
    }

    private function replaceSubmittedAttachments(Request $request, Document $document): void
    {
        foreach ($request->file('replacement_attachments', []) as $fileId => $uploadedFile) {
            $file = $document->files()
                ->whereKey($fileId)
                ->where('type_file', 'attachment')
                ->first();

            if ($file === null) {
                continue;
            }

            $title = $file->attachment_title;
            $order = $file->attachment_order;
            $sourceFileId = $file->source_file_id;
            $documentNumber = $file->document_number;

            $this->replaceSubmittedFileRecord($file, $uploadedFile, 'attachment', $request->user()->id, $title, $order, $sourceFileId, $documentNumber);
        }
    }

    private function storeSubmittedAttachments(Request $request, Document $document): void
    {
        $titles = collect($request->input('attachment_titles', []))->values();
        $orders = collect($request->input('attachment_orders', []))->values();

        foreach (array_values($request->file('attachments', [])) as $index => $uploadedFile) {
            $title = trim((string) ($titles->get($index) ?? ''));
            $order = max(1, (int) ($orders->get($index) ?: ($index + 1)));

            $this->storeSubmittedFile($document, $uploadedFile, 'attachment', $request->user()->id, $title ?: null, $order);
        }
    }

    private function storeSubmittedFile(
        Document $document,
        mixed $file,
        string $type,
        int $uploadedBy,
        ?string $attachmentTitle = null,
        ?int $attachmentOrder = null,
        ?int $sourceFileId = null,
        ?string $documentNumber = null,
    ): void {
        $path = $file->store("documents/{$document->id}", 'local');

        $document->files()->create([
            'type_file' => $type,
            'document_number' => $documentNumber ?? app(DocumentFileNumbering::class)->numberFor($document, $type, $attachmentOrder),
            'attachment_title' => $attachmentTitle,
            'attachment_order' => $attachmentOrder,
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'updated_at' => now(),
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'source_file_id' => $sourceFileId,
            'file_size' => $file->getSize(),
        ]);
    }

    private function replaceSubmittedFileRecord(
        DocumentFile $file,
        mixed $uploadedFile,
        string $type,
        int $uploadedBy,
        ?string $attachmentTitle = null,
        ?int $attachmentOrder = null,
        ?int $sourceFileId = null,
        ?string $documentNumber = null,
    ): void {
        Storage::disk('local')->delete($file->path_file);

        $path = $uploadedFile->store("documents/{$file->t_document_id}", 'local');

        $file->forceFill([
            'type_file' => $type,
            'document_number' => $documentNumber ?? $file->document_number,
            'attachment_title' => $attachmentTitle,
            'attachment_order' => $attachmentOrder,
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'updated_at' => now(),
            'original_file_name' => $uploadedFile->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'source_file_id' => $sourceFileId,
            'file_size' => $uploadedFile->getSize(),
        ])->save();
    }

    private function updateSubmittedAttachments(Document $document, array $titles, array $orders): void
    {
        $fileIds = collect(array_keys($titles))
            ->merge(array_keys($orders))
            ->unique();

        foreach ($fileIds as $fileId) {
            $file = $document->files()
                ->whereKey($fileId)
                ->where('type_file', 'attachment')
                ->first();

            if ($file === null) {
                continue;
            }

            $attributes = [
                'attachment_title' => filled($titles[$fileId] ?? null) ? trim((string) $titles[$fileId]) : null,
                'updated_at' => now(),
            ];

            if (array_key_exists($fileId, $orders) && $file->source_file_id === null) {
                $attributes['attachment_order'] = filled($orders[$fileId] ?? null) ? max(1, (int) $orders[$fileId]) : null;
            }

            $file->forceFill($attributes)->save();
        }
    }

    private function removeSubmittedFiles(Document $document, array $fileIds): void
    {
        if ($fileIds === []) {
            return;
        }

        $document->files()
            ->whereIn('id', $fileIds)
            ->where('type_file', 'attachment')
            ->whereNull('source_file_id')
            ->get()
            ->each(fn (DocumentFile $file) => $this->deleteDocumentFile($file));
    }

    /**
     * @return array<int, string|null>
     */
    private function excludedSubmittedSourceAttachmentNumbers(Document $document, ?array $includedFileIds): array
    {
        if ($includedFileIds === null) {
            return [];
        }

        $includedFileIds = collect($includedFileIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $document->files()
            ->where('type_file', 'attachment')
            ->whereNotNull('source_file_id')
            ->whereNotIn('id', $includedFileIds)
            ->pluck('document_number')
            ->all();
    }

    private function syncSubmittedSourceAttachmentInclusion(Document $document, ?array $includedFileIds): void
    {
        if ($includedFileIds === null) {
            return;
        }

        $includedFileIds = collect($includedFileIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $document->files()
            ->where('type_file', 'attachment')
            ->whereNotNull('source_file_id')
            ->whereNotIn('id', $includedFileIds)
            ->get()
            ->each(fn (DocumentFile $file) => $this->deleteDocumentFile($file));
    }

    private function normalizeSubmittedSourceAttachmentLineage(Document $document): void
    {
        if ($document->request_type !== 'revision' || $document->revised_from === null) {
            return;
        }

        $sourceDocument = $document->revisedFrom;

        if ($sourceDocument === null) {
            return;
        }

        $sourceAttachments = $sourceDocument
            ->availableRevisionSourceAttachments()
            ->filter(fn (DocumentFile $file): bool => filled($file->document_number))
            ->keyBy('document_number');

        if ($sourceAttachments->isEmpty()) {
            return;
        }

        $currentAttachments = $document->files()
            ->where('type_file', 'attachment')
            ->whereNull('source_file_id')
            ->whereNotNull('document_number')
            ->orderBy('id')
            ->get()
            ->groupBy('document_number');

        foreach ($currentAttachments as $documentNumber => $candidates) {
            $source = $sourceAttachments->get($documentNumber);

            if ($source === null) {
                continue;
            }

            $bestCandidate = $candidates
                ->sortByDesc(fn (DocumentFile $candidate): int => $this->sourceAttachmentMatchScore($candidate, $source))
                ->first();

            if ($bestCandidate === null || $this->sourceAttachmentMatchScore($bestCandidate, $source) <= 0) {
                continue;
            }

            $bestCandidate->forceFill([
                'source_file_id' => $source->id,
                'attachment_order' => $source->attachment_order,
                'attachment_title' => filled($bestCandidate->attachment_title)
                    ? $bestCandidate->attachment_title
                    : $source->attachment_title,
                'updated_at' => now(),
            ])->save();
        }

        $document->unsetRelation('files');
    }

    private function sourceAttachmentMatchScore(DocumentFile $candidate, DocumentFile $source): int
    {
        $score = 0;

        if (filled($candidate->attachment_title) && filled($source->attachment_title)
            && Str::lower(trim($candidate->attachment_title)) === Str::lower(trim($source->attachment_title))) {
            $score += 3;
        }

        if (filled($candidate->original_file_name) && filled($source->original_file_name)
            && Str::lower(trim($candidate->original_file_name)) === Str::lower(trim($source->original_file_name))) {
            $score += 2;
        }

        if ($candidate->attachment_order !== null && $candidate->attachment_order === $source->attachment_order) {
            $score += 1;
        }

        return $score;
    }

    private function deleteDocumentFile(DocumentFile $file): void
    {
        Storage::disk('local')->delete($file->path_file);
        $file->delete();
    }

    /**
     * @param  array<int, string|null>  $reservedAttachmentNumbers
     */
    private function renumberSubmittedFiles(Document $document, array $reservedAttachmentNumbers = []): void
    {
        $numbering = app(DocumentFileNumbering::class);

        $document->files()
            ->whereIn('type_file', ['filled_template', 'revision_content', 'imported_document'])
            ->update([
                'document_number' => $numbering->mainDocumentNumber($document),
                'updated_at' => now(),
            ]);

        $document->files()
            ->where('type_file', 'revision_form')
            ->update([
                'document_number' => $numbering->revisionFormNumber($document),
                'updated_at' => now(),
            ]);

        $document->files()
            ->where('type_file', 'attachment')
            ->whereNull('source_file_id')
            ->update([
                'document_number' => null,
                'updated_at' => now(),
            ]);

        $document->unsetRelation('files');
        $numbering->assignMissingNumbers($document);
        $numbering->compactActiveAttachmentNumbers($document, $reservedAttachmentNumbers);
    }

    private function clearApprovalPreviewArtifacts(Document $document): void
    {
        $document->finalArtifacts()
            ->where('artifact_type', DocumentFinalArtifact::TYPE_APPROVAL_PREVIEW)
            ->get()
            ->each(function (DocumentFinalArtifact $artifact): void {
                if (filled($artifact->path_file)) {
                    Storage::disk('local')->delete($artifact->path_file);
                }

                $artifact->delete();
            });
    }

    private function stageApproverIds(Request $request, Document $document, ApprovalFlowStage $stage): Collection
    {
        $inputKey = "stage_approvers.{$stage->id}";

        return collect($request->input($inputKey, []))
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values();
    }

    /**
     * @return array<string, string>|null
     */
    private function approvedStageAssignmentError(Request $request, Document $document, Collection $stages): ?array
    {
        $approvedStatusId = ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id;

        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $field = "stage_approvers.{$stage->id}";
            $requestedUserIds = $this->stageApproverIds($request, $document, $stage);
            $stageApprovals = Approval::query()
                ->where('t_document_id', $document->id)
                ->forApprovalFlowStage($stage)
                ->get();

            if ($stageApprovals->isEmpty()) {
                continue;
            }

            $respondedUserIds = $stageApprovals
                ->filter(fn (Approval $approval): bool => $approval->responded_at !== null)
                ->pluck('user_id')
                ->values();

            if ($respondedUserIds->diff($requestedUserIds)->isNotEmpty()) {
                return [$field => "Approver tahap {$stage->stage_order} yang sudah memberikan respon tidak boleh dihapus atau diganti."];
            }

            $approvedUserIds = $stageApprovals
                ->where('m_approval_status_id', $approvedStatusId)
                ->pluck('user_id')
                ->values();

            if ($approvedUserIds->diff($requestedUserIds)->isNotEmpty()) {
                return [$field => "Approver tahap {$stage->stage_order} yang sudah approve tidak boleh dihapus atau diganti."];
            }

            $isStageFullyApproved = $stageApprovals->every(
                fn (Approval $approval): bool => $approval->m_approval_status_id === $approvedStatusId,
            );

            if (! $isStageFullyApproved) {
                continue;
            }

            $existingUserIds = $stageApprovals->pluck('user_id')->sort()->values();

            if ($existingUserIds->all() !== $requestedUserIds->sort()->values()->all()) {
                return [$field => "Tahap {$stage->stage_order} sudah approved dan tidak boleh diubah."];
            }
        }

        return null;
    }

    private function assignmentDiffers(Request $request, Document $document, Collection $stages): bool
    {
        foreach ($stages as $stage) {
            $stageLabel = $stage->display_label ?: 'Approval';
            $currentUserIds = Approval::query()
                ->where('t_document_id', $document->id)
                ->forApprovalFlowStage($stage)
                ->pluck('user_id')
                ->sort()
                ->values();
            $requestedUserIds = $this->stageApproverIds($request, $document, $stage)
                ->sort()
                ->values();

            if ($currentUserIds->all() !== $requestedUserIds->all()) {
                return true;
            }
        }

        return false;
    }

    private function advanceApprovalFlow(Document $document): ?Document
    {
        if ($this->activateNextStageIfCurrentStageComplete($document)) {
            return null;
        }

        return $this->markDocumentApprovedWhenComplete($document);
    }

    private function activateNextStageIfCurrentStageComplete(Document $document): bool
    {
        $pendingStatus = ApprovalStatus::findByCode(ApprovalStatus::PENDING);
        $waitingStatus = ApprovalStatus::findByCode(ApprovalStatus::WAITING);
        $approvedStatus = ApprovalStatus::findByCode(ApprovalStatus::APPROVED);
        $stages = $this->approvalFlowStages($document);

        foreach ($stages as $stage) {
            $stageApprovals = Approval::query()
                ->where('t_document_id', $document->id)
                ->forApprovalFlowStage($stage);

            if ((clone $stageApprovals)->where('m_approval_status_id', $pendingStatus->id)->exists()) {
                return false;
            }

            if (
                (clone $stageApprovals)->exists()
                && ! (clone $stageApprovals)->where('m_approval_status_id', '!=', $approvedStatus->id)->exists()
            ) {
                continue;
            }

            $activated = (clone $stageApprovals)
                ->where('m_approval_status_id', $waitingStatus->id)
                ->update([
                    'm_approval_status_id' => $pendingStatus->id,
                    'assigned_at' => now(),
                ]);

            return $activated > 0;
        }

        return false;
    }

    private function markDocumentApprovedWhenComplete(Document $document): ?Document
    {
        if (! $this->isApprovalComplete($document)) {
            return null;
        }

        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);

        if ($document->revised_from !== null && $document->request_type !== 'obsolete') {
            return $this->finalizeRevisionApproval($document, $approvedStatus);
        }

        $approvedAt = now();

        $document->update([
            'm_status_document_id' => $approvedStatus->id,
            'tanggal_terbit' => $document->tanggal_terbit ?? $approvedAt->toDateString(),
            'approved_at' => $approvedAt,
            'rejected_at' => null,
        ]);

        $document->refresh();

        if ($document->request_type === 'obsolete') {
            $this->obsoleteSourceMasterDocument($document);

            return null;
        }

        return $document->refresh();
    }

    private function finalizeRevisionApproval(Document $document, StatusDocument $approvedStatus): ?Document
    {
        if ($document->revised_from === null || $document->request_type === 'obsolete') {
            return null;
        }

        $obsoleteStatus = StatusDocument::findByName(StatusDocument::OBSOLETE);
        $terminalStatusIds = StatusDocument::query()
            ->whereIn('nama_status', [
                StatusDocument::APPROVED,
                StatusDocument::OBSOLETE,
                StatusDocument::REJECTED,
                StatusDocument::CANCELLED,
            ])
            ->pluck('id')
            ->all();
        $familyIds = $document->revisionFamily()->pluck('id')->all();
        /** @var Collection<int, Document> $lockedFamily */
        $lockedFamily = Document::query()
            ->whereIn('id', $familyIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var Document|null $lockedDocument */
        $lockedDocument = $lockedFamily->firstWhere('id', $document->id);

        /** @var Document|null $source */
        $source = $lockedDocument !== null
            ? $lockedFamily->firstWhere('id', $lockedDocument->revised_from)
            : null;

        if ($lockedDocument === null || $source === null) {
            throw new ConflictHttpException('Pengajuan revisi sudah tidak valid.');
        }

        if (in_array($lockedDocument->m_status_document_id, $terminalStatusIds, true)) {
            throw new ConflictHttpException('Pengajuan revisi sudah diproses sebelumnya.');
        }

        if ($source->m_status_document_id !== $approvedStatus->id) {
            throw new ConflictHttpException('Master sumber sudah berubah. Muat ulang halaman sebelum melanjutkan approval.');
        }

        $conflictingMaster = $lockedFamily
            ->first(fn (Document $revision): bool => $revision->id !== $source->id
                && $revision->id !== $lockedDocument->id
                && $revision->m_status_document_id === $approvedStatus->id
                && $revision->request_type !== 'obsolete'
                && $revision->numeric_revision >= $lockedDocument->numeric_revision);

        if ($conflictingMaster !== null) {
            throw new ConflictHttpException('Family dokumen sudah memiliki master aktif lain.');
        }

        $approvedAt = now();

        $lockedDocument->update([
            'm_document_level_id' => $source->m_document_level_id,
            'm_status_document_id' => $approvedStatus->id,
            'm_document_types_id' => $source->m_document_types_id,
            'm_proses_bisnis_id' => $document->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $document->m_proses_fungsi_id,
            'user_id' => $document->user_id,
            'official_preparer_id' => $document->official_preparer_id,
            'nomor_dokumen' => $source->nomor_dokumen,
            'nomor_lembar_revisi' => $lockedDocument->nomor_lembar_revisi
                ?: $this->revisionFormNumber($source, $lockedDocument->numeric_revision),
            'tanggal_terbit' => $lockedDocument->tanggal_terbit ?? $approvedAt->toDateString(),
            'approved_at' => $approvedAt,
            'rejected_at' => null,
        ]);

        Document::query()
            ->whereIn('id', $lockedFamily->pluck('id'))
            ->where('id', '!=', $lockedDocument->id)
            ->where('m_status_document_id', $approvedStatus->id)
            ->where(function ($query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', '!=', 'obsolete');
            })
            ->update([
                'm_status_document_id' => $obsoleteStatus->id,
                'obsolete_at' => $approvedAt,
            ]);

        DocumentRelation::supersedeDocumentSourceWithDocument($source, $lockedDocument, $lockedDocument->user_id);

        if ($lockedDocument->documentLevel?->kode === 'level-3') {
            DocumentRelation::copyReferenceToDocumentSource($lockedDocument, $source, $lockedDocument->user_id);
        }

        return $lockedDocument->refresh();
    }


    private function autoGenerateFinalDocumentAfterCommit(Document $document, ?User $generatedBy): void
    {
        $documentId = $document->id;
        $generatedById = $generatedBy?->id;
        $callback = fn () => app(AutoGenerateFinalDocument::class)
            ->generateIfNeeded($documentId, $generatedById);

        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }

    private function revisionFormNumber(Document $source, int $revision): string
    {
        return (string) app(DocumentFileNumbering::class)->revisionFormNumber($source);
    }

    private function obsoleteSourceMasterDocument(Document $document): void
    {
        if ($document->revised_from === null) {
            return;
        }

        $obsoleteStatus = StatusDocument::findByName(StatusDocument::OBSOLETE);

        Document::query()
            ->whereKey($document->revised_from)
            ->where(function ($query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', '!=', 'obsolete');
            })
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::APPROVED))
            ->update([
                'm_status_document_id' => $obsoleteStatus->id,
                'obsolete_at' => $document->approved_at ?? now(),
            ]);
    }

    private function obsoletePreviousApprovedMasterDocuments(Document $document, StatusDocument $approvedStatus): void
    {
        if ($document->revised_from === null) {
            return;
        }

        $obsoleteStatus = StatusDocument::findByName(StatusDocument::OBSOLETE);

        $document->revisionFamily()
            ->where('id', '!=', $document->id)
            ->filter(fn (Document $revision): bool => $revision->request_type !== 'obsolete')
            ->where('m_status_document_id', $approvedStatus->id)
            ->each(function (Document $revision) use ($obsoleteStatus): void {
                $revision->update([
                    'm_status_document_id' => $obsoleteStatus->id,
                    'obsolete_at' => now(),
                ]);
            });
    }

    private function isApprovalComplete(Document $document): bool
    {
        $approvedStatusId = ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id;
        $rejectedStatusId = ApprovalStatus::findByCode(ApprovalStatus::REJECTED)->id;
        $stages = $this->approvalFlowStages($document);

        $approvalQuery = Approval::query()->where('t_document_id', $document->id);

        if ((clone $approvalQuery)->where('m_approval_status_id', $rejectedStatusId)->exists()) {
            return false;
        }

        if ($stages->isEmpty()) {
            return (clone $approvalQuery)->exists()
                && ! (clone $approvalQuery)
                    ->where('m_approval_status_id', '!=', $approvedStatusId)
                    ->exists();
        }

        foreach ($stages as $stage) {
            $stageApprovals = Approval::query()
                ->where('t_document_id', $document->id)
                ->forApprovalFlowStage($stage);

            if (! (clone $stageApprovals)->exists()) {
                return false;
            }

            if ((clone $stageApprovals)->where('m_approval_status_id', '!=', $approvedStatusId)->exists()) {
                return false;
            }
        }

        return true;
    }

    private function activeStageOrderForAssignment(Document $document, Collection $stages): int
    {
        $approvedStatusId = ApprovalStatus::findByCode(ApprovalStatus::APPROVED)->id;

        foreach ($stages as $stage) {
            $stageApprovals = Approval::query()
                ->where('t_document_id', $document->id)
                ->forApprovalFlowStage($stage);

            if (
                ! (clone $stageApprovals)->exists()
                || (clone $stageApprovals)->where('m_approval_status_id', '!=', $approvedStatusId)->exists()
            ) {
                return $stage->stage_order;
            }
        }

        return $stages->last()?->stage_order ?? 1;
    }
}
