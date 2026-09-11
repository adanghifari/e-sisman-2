<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentNumberingSetup;
use App\Models\DocumentNumberRegistry;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Support\DocumentFiles\DocumentFileNumbering;
use App\Support\FinalDocuments\AutoGenerateApprovalPreview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        return view('document-management.create.index', [
            'draftCount' => $this->draftQuery($request)->count(),
        ]);
    }

    public function drafts(Request $request): View
    {
        return view('document-management.create.drafts', [
            'drafts' => $this->draftQuery($request)
                ->with(['documentLevel', 'businessProcess', 'businessFunction', 'departments', 'files'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(Request $request, string $level): View
    {
        $resubmissionSource = $this->resubmissionSourceForRequest($request, $level);
        $revisionSource = $resubmissionSource?->revisedFrom
            ?: $this->revisionSourceForRequest($request, $level);

        abort_if($level === 'level-4' && $revisionSource === null, 404);

        return view('document-management.create.level', [
            'levelKey' => $level,
            'revisionSource' => $revisionSource,
            'resubmissionSource' => $resubmissionSource,
            'procedureReferences' => $level === 'level-3'
                ? $this->activeProcedureReferences()
                : collect(),
        ]);
    }

    public function resubmitRejected(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing(['status', 'documentLevel', 'revisedFrom.status', 'revisedFrom.documentLevel']);
        $this->authorizeRejectedResubmissionAccess($request, $document);

        $level = $document->documentLevel?->kode;
        abort_unless(filled($level) && array_key_exists($level, config('document-levels')), 404);

        return redirect()->route('documents.create.level', [
            'level' => $level,
            'resubmitted_from' => $document->id,
        ]);
    }

    public function editDraft(Request $request, mixed $document): View
    {
        $document = $document instanceof Document
            ? $document
            : Document::query()->findOrFail($document);

        $this->authorizeDraftAccess($request, $document);
        $document->loadMissing([
            'status',
            'documentLevel',
            'departments',
            'files',
            'officialPreparer',
            'outgoingRelations',
            'resubmittedFrom.files',
            'resubmittedFrom.status',
            'revisedFrom.status',
            'revisedFrom.documentLevel',
            'revisedFrom.businessProcess',
            'revisedFrom.businessFunction',
            'revisedFrom.departments',
            'revisedFrom.outgoingRelations',
        ]);

        $level = $document->documentLevel?->kode;
        abort_unless(filled($level) && array_key_exists($level, config('document-levels')), 404);

        $revisionSource = $document->revisedFrom;

        return view('document-management.create.level', [
            'levelKey' => $level,
            'draft' => $document,
            'revisionSource' => $revisionSource,
            'procedureReferences' => $level === 'level-3'
                ? $this->activeProcedureReferences()
                : collect(),
        ]);
    }

    public function destroyDraft(Request $request, mixed $document): RedirectResponse
    {
        $document = $document instanceof Document
            ? $document
            : Document::query()->with(['status', 'files'])->findOrFail($document);

        $this->authorizeDraftAccess($request, $document);

        DB::transaction(function () use ($document): void {
            $document->files->each(function ($file): void {
                Storage::disk('local')->delete($file->path_file);
                $file->delete();
            });

            $document->departments()->detach();
            $document->delete();
        });

        return redirect()
            ->route('documents.create.drafts')
            ->with('status', 'Draft berhasil dihapus.');
    }

    public function store(Request $request, string $level): RedirectResponse
    {
        $draft = $this->draftForRequest($request);
        $resubmissionSource = $draft?->resubmittedFrom ?: $this->resubmissionSourceForRequest($request, $level);
        $documentLevel = DocumentLevel::query()
            ->where('kode', $level)
            ->firstOrFail();

        $documentType = DocumentType::query()
            ->whereIn('nama_types', $this->documentTypeNamesForLevel($level))
            ->orderByRaw(
                'case nama_types '.
                collect($this->documentTypeNamesForLevel($level))
                    ->map(fn (string $name, int $index): string => "when ? then {$index}")
                    ->implode(' ').
                ' else 999 end',
                $this->documentTypeNamesForLevel($level),
            )
            ->firstOrFail();

        $validated = $request->validate($this->validationRulesForLevel($level, $draft, $resubmissionSource));
        $revisionSource = $draft?->revisedFrom
            ?: $resubmissionSource?->revisedFrom
            ?: $this->revisionSourceForRequest($request, $level);

        abort_if($level === 'level-4' && $revisionSource === null, 404);
        abort_if($draft !== null && $draft->documentLevel?->kode !== $level, 404);

        if ($revisionSource !== null) {
            $validated['m_proses_bisnis_id'] = $revisionSource->m_proses_bisnis_id;
            $validated['m_proses_fungsi_id'] = $revisionSource->m_proses_fungsi_id;
            $validated['department_ids'] = collect($revisionSource->departments)->pluck('id')->all();
            $validated['reference'] = $revisionSource->procedureReferenceValue();
            $validated['nama_dokumen'] = $validated['nama_dokumen'] ?? $revisionSource->nama_dokumen;
        }

        if (($validated['submit_action'] ?? null) === 'draft') {
            $validated['nama_dokumen'] = filled($validated['nama_dokumen'] ?? null)
                ? $validated['nama_dokumen']
                : 'Draft tanpa judul';
            $validated['department_ids'] = $validated['department_ids'] ?? [];
        }

        $documentRevision = $revisionSource !== null
            ? null
            : ($resubmissionSource !== null
                ? $resubmissionSource->numeric_revision
                : $this->normalizeRevision($validated['nomor_revisi'] ?? null));
        $documentNumber = $revisionSource !== null
            ? null
            : ($resubmissionSource?->nomor_dokumen ?: $this->buildDocumentNumber($documentLevel, $validated));
        $revisionFormNumber = null;

        $status = StatusDocument::findByName(
            $validated['submit_action'] === 'submit'
                ? StatusDocument::PROPOSED
                : StatusDocument::DRAFT,
        );

        $savedDocument = null;
        $documentNumberLockAcquired = false;

        try {
            DB::transaction(function () use ($request, $validated, $documentNumber, $revisionFormNumber, $documentRevision, $documentLevel, $documentType, $status, $level, $revisionSource, $resubmissionSource, $draft, &$savedDocument, &$documentNumberLockAcquired): void {
                $lockedRevisionSource = null;
                $documentAttributes = $validated;
                $currentDocumentNumber = $documentNumber;
                $currentRevisionFormNumber = $revisionFormNumber;
                $currentDocumentRevision = $documentRevision;
                $resubmittedFromId = $resubmissionSource?->id;

                if ($revisionSource !== null) {
                    $lockedRevisionSource = Document::query()
                        ->whereKey($revisionSource->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $lockedRevisionSource->load([
                        'status',
                        'documentLevel',
                        'businessProcess',
                        'businessFunction',
                        'departments',
                        'files',
                        'outgoingRelations',
                        'revisedFrom.documentLevel',
                    ]);

                    if ($lockedRevisionSource->status?->nama_status !== StatusDocument::APPROVED) {
                        throw ValidationException::withMessages([
                            'revised_from' => 'Master sumber sudah berubah. Muat ulang halaman sebelum membuat revisi.',
                        ]);
                    }

                    if (! $this->userCanRequestRevision($request, $lockedRevisionSource)) {
                        abort(403);
                    }

                    if ($draft === null && $this->hasActiveRevisionRequest($lockedRevisionSource)) {
                        throw ValidationException::withMessages([
                            'revised_from' => 'Dokumen ini masih memiliki pengajuan revisi aktif. Selesaikan atau batalkan revisi tersebut terlebih dahulu.',
                        ]);
                    }

                    $currentDocumentRevision = match (true) {
                        $draft !== null && $draft->revised_from !== null => $draft->numeric_revision,
                        $resubmissionSource !== null && $resubmissionSource->revised_from !== null => $resubmissionSource->numeric_revision,
                        default => $this->nextRevisionNumber($lockedRevisionSource),
                    };
                    $currentDocumentNumber = $this->revisionSourceMasterNumber($lockedRevisionSource);
                    $currentRevisionFormNumber = $draft?->nomor_lembar_revisi
                        ?: $resubmissionSource?->nomor_lembar_revisi
                        ?: $this->buildRevisionFormNumber($lockedRevisionSource, (int) $currentDocumentRevision);

                    $documentAttributes['m_proses_bisnis_id'] = $lockedRevisionSource->m_proses_bisnis_id;
                    $documentAttributes['m_proses_fungsi_id'] = $lockedRevisionSource->m_proses_fungsi_id;
                    $documentAttributes['department_ids'] = $lockedRevisionSource->departments->pluck('id')->all();
                    $documentAttributes['reference'] = $lockedRevisionSource->procedureReferenceValue();
                    $documentAttributes['nama_dokumen'] = $documentAttributes['nama_dokumen'] ?? $lockedRevisionSource->nama_dokumen;
                } elseif ($currentDocumentNumber !== null) {
                    if (($documentAttributes['submit_action'] ?? null) === 'submit') {
                        $this->assertDocumentNumberAllowedForV2($currentDocumentNumber);
                    }

                    $documentNumberLockAcquired = $this->acquireDocumentNumberLock($currentDocumentNumber);
                    $sameNumberDocuments = $this->lockedDocumentsForNumber($currentDocumentNumber);
                    $resubmittedFromId = $resubmissionSource?->id
                        ?: $this->resubmittedFromIdForReusableNumber($sameNumberDocuments, $draft);

                    if (($documentAttributes['submit_action'] ?? null) === 'submit' && $resubmittedFromId === null) {
                        $this->assertDocumentNumberFollowsRegistrySequence($currentDocumentNumber);
                    }
                }

                $submittedAt = $documentAttributes['submit_action'] === 'submit' ? now() : null;
                $attributes = [
                    'm_document_level_id' => $documentLevel->id,
                    'm_status_document_id' => $status->id,
                    'm_document_types_id' => $documentType->id,
                    'm_proses_bisnis_id' => $documentAttributes['m_proses_bisnis_id'] ?? null,
                    'm_proses_fungsi_id' => $documentAttributes['m_proses_fungsi_id'] ?? null,
                    'user_id' => $request->user()->id,
                    'official_preparer_id' => $documentAttributes['official_preparer_id'] ?? null,
                    'revised_from' => $lockedRevisionSource?->id,
                    'resubmitted_from' => $resubmittedFromId,
                    'request_type' => $revisionSource !== null ? 'revision' : null,
                    'nama_dokumen' => $documentAttributes['nama_dokumen'],
                    'nomor_dokumen' => $currentDocumentNumber,
                    'nomor_lembar_revisi' => $currentRevisionFormNumber,
                    'nomor_revisi' => Document::formatRevisionNumber($currentDocumentRevision),
                    'catatan_revisi' => $documentAttributes['catatan_revisi'] ?? null,
                    'tanggal_terbit' => $documentAttributes['tanggal_terbit'] ?? null,
                    'submitted_at' => $submittedAt ?? $draft?->submitted_at,
                    'approved_at' => null,
                    'rejected_at' => null,
                    'cancelled_at' => null,
                ];

                $document = $draft;

                if ($document === null) {
                    $attributes['created_at'] = now();
                    $document = Document::create($attributes);
                } else {
                    $document->update($attributes);
                }

                $document->unsetRelation('status');
                $document->unsetRelation('files');

                $document->departments()->sync($documentAttributes['department_ids'] ?? []);
                $this->syncProcedureReference($document, $level, $documentAttributes['reference'] ?? null, $request->user()->id);

                if ($resubmissionSource !== null) {
                    $this->copyResubmissionSourceFiles(
                        $resubmissionSource,
                        $document,
                        $documentAttributes['remove_existing_files'] ?? [],
                        $documentAttributes['existing_attachment_titles'] ?? [],
                        $documentAttributes['existing_attachment_orders'] ?? [],
                    );
                    DocumentRelation::copyReferenceToDocumentSource($document, $resubmissionSource, $request->user()->id);
                }

                $this->removeExistingDocumentFiles($document, $documentAttributes['remove_existing_files'] ?? []);
                $this->updateExistingAttachments(
                    $document,
                    $documentAttributes['existing_attachment_titles'] ?? [],
                    $documentAttributes['existing_attachment_orders'] ?? [],
                );

                if ($lockedRevisionSource !== null) {
                    $this->syncRevisionSourceAttachments(
                        $request,
                        $document,
                        $lockedRevisionSource,
                    );
                }

                if ($request->hasFile('imported_document')) {
                    $this->replaceSingleDocumentFile($document, 'imported_document');
                    $this->storeDocumentFile($document, $request->file('imported_document'), 'imported_document', $request->user()->id);
                }

                if ($request->hasFile('filled_template')) {
                    $this->replaceSingleDocumentFile($document, 'filled_template');
                    $this->storeDocumentFile($document, $request->file('filled_template'), 'filled_template', $request->user()->id);
                }

                if ($request->hasFile('filled_template_word')) {
                    $this->replaceSingleDocumentFile($document, 'filled_template_word');
                    $this->storeDocumentFile($document, $request->file('filled_template_word'), 'filled_template_word', $request->user()->id);
                }

                if ($request->hasFile('revision_content')) {
                    $this->replaceSingleDocumentFile($document, 'revision_content');
                    $this->storeDocumentFile($document, $request->file('revision_content'), 'revision_content', $request->user()->id);
                }

                if ($request->hasFile('revision_content_word')) {
                    $this->replaceSingleDocumentFile($document, 'revision_content_word');
                    $this->storeDocumentFile($document, $request->file('revision_content_word'), 'revision_content_word', $request->user()->id);
                }

                if ($request->hasFile('revision_form')) {
                    $this->replaceSingleDocumentFile($document, 'revision_form');
                    $this->storeDocumentFile($document, $request->file('revision_form'), 'revision_form', $request->user()->id);
                }

                if ($request->hasFile('revision_form_word')) {
                    $this->replaceSingleDocumentFile($document, 'revision_form_word');
                    $this->storeDocumentFile($document, $request->file('revision_form_word'), 'revision_form_word', $request->user()->id);
                }

                $this->storeAttachmentFiles($request, $document);

                if ($submittedAt !== null) {
                    $document->snapshotOfficialPreparer();
                    $this->claimTDocumentNumber($document, $request->user()->id);
                    $numbering = app(DocumentFileNumbering::class);
                    $numbering->assignMissingNumbers($document);
                    $numbering->compactActiveAttachmentNumbers($document);
                    $this->recordOfficialPreparerApproval($document, $request->user()->id, $submittedAt);
                }

                $savedDocument = $document;

                if ($submittedAt !== null) {
                    $documentId = $document->id;
                    $generatedById = $request->user()->id;

                    DB::afterCommit(fn () => app(AutoGenerateApprovalPreview::class)
                        ->generateIfNeeded($documentId, $generatedById));
                }
            });
        } finally {
            if ($documentNumberLockAcquired && $documentNumber !== null) {
                $this->releaseDocumentNumberLock($documentNumber);
            }
        }

        if ($validated['submit_action'] === 'submit') {
            return redirect()
                ->route('documents.create')
                ->with('document_success', [
                    'title' => 'Dokumen berhasil disubmit',
                    'message' => 'Dokumen akan segera diproses oleh tim terkait.',
                ]);
        }

        $redirectParameters = $revisionSource !== null
            ? [$level, 'revised_from' => $revisionSource->id]
            : [$level];

        if ($savedDocument !== null) {
            return redirect()
                ->route('documents.create.drafts')
                ->with('status', 'Draft berhasil disimpan.');
        }

        return redirect()
            ->route('documents.create.level', $redirectParameters)
            ->with('status', 'Dokumen berhasil disimpan sebagai draft.');
    }

    public function autosave(Request $request, string $level): JsonResponse
    {
        $documentLevel = DocumentLevel::query()
            ->where('kode', $level)
            ->firstOrFail();
        $documentType = DocumentType::query()
            ->whereIn('nama_types', $this->documentTypeNamesForLevel($level))
            ->orderByRaw(
                'case nama_types '.
                collect($this->documentTypeNamesForLevel($level))
                    ->map(fn (string $name, int $index): string => "when ? then {$index}")
                    ->implode(' ').
                ' else 999 end',
                $this->documentTypeNamesForLevel($level),
            )
            ->firstOrFail();
        $validated = $request->validate($this->autosaveValidationRulesForLevel($level));
        $resubmissionSource = $this->resubmissionSourceForRequest($request, $level);
        $revisionSource = $resubmissionSource?->revisedFrom
            ?: $this->autosaveRevisionSourceForRequest($request, $level);

        if (! $this->hasAutosavePayload($request, $validated)) {
            return response()->json([
                'saved' => false,
                'draft_id' => $request->integer('draft_id') ?: null,
            ]);
        }

        if ($level === 'level-1') {
            $validated['nomor_revisi'] = '00.00';
        }

        if ($revisionSource !== null) {
            $validated['m_proses_bisnis_id'] = $revisionSource->m_proses_bisnis_id;
            $validated['m_proses_fungsi_id'] = $revisionSource->m_proses_fungsi_id;
            $validated['department_ids'] = collect($revisionSource->departments)->pluck('id')->all();
            $validated['reference'] = $revisionSource->procedureReferenceValue();
            $validated['nama_dokumen'] = $validated['nama_dokumen'] ?? $revisionSource->nama_dokumen;
        }

        $validated['nama_dokumen'] = filled($validated['nama_dokumen'] ?? null)
            ? $validated['nama_dokumen']
            : 'Draft tanpa judul';
        $validated['department_ids'] = $validated['department_ids'] ?? [];

        $documentNumber = $revisionSource !== null
            ? $this->revisionSourceMasterNumber($revisionSource)
            : ($resubmissionSource?->nomor_dokumen ?: $this->buildDocumentNumber($documentLevel, $validated));
        $draft = $this->autosaveDraftForRequest($request, $documentLevel, $revisionSource, $documentNumber, $resubmissionSource);
        $documentRevision = $revisionSource !== null
            ? ($resubmissionSource !== null
                ? $resubmissionSource->numeric_revision
                : $this->autosaveRevisionNumber($draft, $revisionSource))
            : ($resubmissionSource !== null
                ? $resubmissionSource->numeric_revision
                : $this->normalizeRevision($validated['nomor_revisi'] ?? null));
        $revisionFormNumber = $revisionSource !== null
            ? ($draft?->nomor_lembar_revisi ?: $this->buildRevisionFormNumber($revisionSource, (int) $documentRevision))
            : null;
        $status = StatusDocument::findByName(StatusDocument::DRAFT);
        $savedDocument = null;

        DB::transaction(function () use ($request, $validated, $documentLevel, $documentType, $status, $revisionSource, $resubmissionSource, $draft, $documentNumber, $documentRevision, $revisionFormNumber, &$savedDocument): void {
            $attributes = [
                'm_document_level_id' => $documentLevel->id,
                'm_status_document_id' => $status->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $validated['m_proses_bisnis_id'] ?? null,
                'm_proses_fungsi_id' => $validated['m_proses_fungsi_id'] ?? null,
                'user_id' => $request->user()->id,
                'official_preparer_id' => $validated['official_preparer_id'] ?? null,
                'revised_from' => $revisionSource?->id,
                'resubmitted_from' => $draft?->resubmitted_from ?: $resubmissionSource?->id,
                'request_type' => $revisionSource !== null ? 'revision' : null,
                'nama_dokumen' => $validated['nama_dokumen'],
                'nomor_dokumen' => $documentNumber,
                'nomor_lembar_revisi' => $revisionFormNumber,
                'nomor_revisi' => Document::formatRevisionNumber($documentRevision),
                'catatan_revisi' => $validated['catatan_revisi'] ?? null,
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? null,
                'submitted_at' => null,
            ];

            $document = $draft;

            if ($document === null) {
                $attributes['created_at'] = now();
                $document = Document::create($attributes);
            } else {
                $document->update($attributes);
            }

            $document->unsetRelation('status');
            $document->unsetRelation('files');

            $document->departments()->sync($validated['department_ids'] ?? []);
            $this->syncProcedureReference($document, $documentLevel->kode, $validated['reference'] ?? null, $request->user()->id);
            if ($resubmissionSource !== null) {
                $this->copyResubmissionSourceFiles(
                    $resubmissionSource,
                    $document,
                    $validated['remove_existing_files'] ?? [],
                    $validated['existing_attachment_titles'] ?? [],
                    $validated['existing_attachment_orders'] ?? [],
                );
                DocumentRelation::copyReferenceToDocumentSource($document, $resubmissionSource, $request->user()->id);
            }
            $this->storeAutosaveFiles($request, $document);

            $savedDocument = $document;
        });

        return response()->json([
            'saved' => true,
            'draft_id' => $savedDocument?->id,
            'saved_at' => now()->format('H:i:s'),
            'edit_url' => $savedDocument ? route('documents.create.drafts.edit', $savedDocument) : null,
        ]);
    }

    protected function documentTypeNameForLevel(string $level): string
    {
        return $this->documentTypeNamesForLevel($level)[0] ?? 'IK';
    }

    /**
     * @return array<int, string>
     */
    protected function documentTypeNamesForLevel(string $level): array
    {
        return [
            'level-1' => ['Manual'],
            'level-2' => ['Prosedur'],
            'level-3' => ['IK', 'Instruksi Kerja'],
            'level-4' => ['Form'],
        ][$level] ?? ['IK', 'Instruksi Kerja'];
    }

    protected function validationRulesForLevel(string $level, ?Document $draft = null, ?Document $resubmissionSource = null): array
    {
        $submitAction = request('submit_action', null);
        $requiresSubmittedFile = $submitAction !== 'draft';
        $isDraftAction = $submitAction === 'draft';
        $removedExistingFileIds = request('remove_existing_files', []);

        if ($level === 'level-1') {
            return [
                'nama_dokumen' => [$isDraftAction ? 'nullable' : 'required', 'string', 'max:255'],
                'nomor_dokumen_suffix' => $this->manualDocumentNumberSuffixRules($isDraftAction),
                'nomor_revisi' => ['required', Rule::in(['00.00'])],
                'tanggal_terbit' => [$isDraftAction ? 'nullable' : 'required', 'date'],
                'catatan_revisi' => ['nullable', 'string', 'max:1000'],
                'official_preparer_id' => [$submitAction === 'submit' ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')],
                'imported_document' => [$isDraftAction || $this->documentHasReusableFile($draft, $resubmissionSource, 'imported_document', $removedExistingFileIds) ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:10240'],
                'submit_action' => ['required', Rule::in(['draft', 'submit'])],
                'revised_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
                'resubmitted_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
                'draft_id' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
                'remove_existing_files' => ['nullable', 'array'],
                'remove_existing_files.*' => ['integer', Rule::exists('t_document_files', 'id')],
                'existing_attachment_titles' => ['nullable', 'array'],
                'existing_attachment_titles.*' => ['nullable', 'string', 'max:255'],
                'existing_attachment_orders' => ['nullable', 'array'],
                'existing_attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:10'],
            ];
        }

        if ($level === 'level-4') {
            return [
                'nama_dokumen' => [$isDraftAction ? 'nullable' : 'required', 'string', 'max:255'],
                'm_proses_bisnis_id' => [$isDraftAction ? 'nullable' : 'required', 'integer', Rule::exists('m_proses_bisnis', 'id')],
                'm_proses_fungsi_id' => [$isDraftAction ? 'nullable' : 'required', 'integer', Rule::exists('m_proses_fungsi', 'id')],
                'reference' => ['nullable', 'string', 'max:255'],
                'department_ids' => [$isDraftAction ? 'nullable' : 'required', 'array', 'min:1'],
                'department_ids.*' => ['required', 'integer', Rule::exists('departments', 'id')],
                'official_preparer_id' => [$submitAction === 'submit' ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')],
                'nomor_dokumen_suffix' => $this->documentNumberSuffixRules(true),
                'tanggal_terbit' => ['nullable', 'date'],
                'revision_content' => [$requiresSubmittedFile && ! $this->documentHasReusableFile($draft, $resubmissionSource, 'revision_content', $removedExistingFileIds) ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:10240'],
                'revision_content_word' => [$requiresSubmittedFile && ! $this->documentHasReusableFile($draft, $resubmissionSource, 'revision_content_word', $removedExistingFileIds) ? 'required' : 'nullable', 'file', 'mimes:doc,docx', 'max:10240'],
                'revision_form' => [$requiresSubmittedFile && ! $this->documentHasReusableFile($draft, $resubmissionSource, 'revision_form', $removedExistingFileIds) ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:10240'],
                'revision_form_word' => [$requiresSubmittedFile && ! $this->documentHasReusableFile($draft, $resubmissionSource, 'revision_form_word', $removedExistingFileIds) ? 'required' : 'nullable', 'file', 'mimes:doc,docx', 'max:10240'],
                'attachments' => ['nullable', 'array', 'max:10'],
                'attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
                'attachment_titles' => ['nullable', 'array', 'max:10'],
                'attachment_titles.*' => ['required_with:attachments.*', 'string', 'max:255'],
                'attachment_orders' => ['nullable', 'array', 'max:10'],
                'attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:10'],
                'included_attachment_ids' => ['nullable', 'array', 'max:20'],
                'included_attachment_ids.*' => ['integer'],
                'revised_attachments' => ['nullable', 'array', 'max:20'],
                'revised_attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
                'submit_action' => ['required', Rule::in(['draft', 'submit'])],
                'revised_from' => [$draft !== null || $resubmissionSource !== null ? 'nullable' : 'required_without:imported_source', 'nullable', 'integer', Rule::exists('t_document', 'id')],
                'imported_source' => [$draft !== null || $resubmissionSource !== null ? 'nullable' : 'required_without:revised_from', 'nullable', 'integer', Rule::exists('t_document', 'id')],
                'resubmitted_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
                'draft_id' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
                'remove_existing_files' => ['nullable', 'array'],
                'remove_existing_files.*' => ['integer', Rule::exists('t_document_files', 'id')],
                'existing_attachment_titles' => ['nullable', 'array'],
                'existing_attachment_titles.*' => ['nullable', 'string', 'max:255'],
                'existing_attachment_orders' => ['nullable', 'array'],
                'existing_attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:10'],
            ];
        }

        return [
            'nama_dokumen' => [$isDraftAction ? 'nullable' : 'required', 'string', 'max:255'],
            'm_proses_bisnis_id' => [$isDraftAction ? 'nullable' : 'required', 'integer', Rule::exists('m_proses_bisnis', 'id')],
            'm_proses_fungsi_id' => [$isDraftAction ? 'nullable' : 'required', 'integer', Rule::exists('m_proses_fungsi', 'id')],
            'reference' => $isDraftAction ? ['nullable', 'string', 'max:255'] : $this->referenceRulesForLevel($level),
            'department_ids' => [$isDraftAction ? 'nullable' : 'required', 'array', 'min:1'],
            'department_ids.*' => ['required', 'integer', Rule::exists('departments', 'id')],
            'official_preparer_id' => [$submitAction === 'submit' ? 'required' : 'nullable', 'integer', Rule::exists('users', 'id')],
            'nomor_dokumen_suffix' => $this->documentNumberSuffixRules($isDraftAction),
            'tanggal_terbit' => ['nullable', 'date'],
            'filled_template' => [$requiresSubmittedFile && ! $this->documentHasReusableFile($draft, $resubmissionSource, 'filled_template', $removedExistingFileIds) ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'filled_template_word' => [$requiresSubmittedFile && ! $this->documentHasReusableFile($draft, $resubmissionSource, 'filled_template_word', $removedExistingFileIds) ? 'required' : 'nullable', 'file', 'mimes:doc,docx', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
            'attachment_titles' => ['nullable', 'array', 'max:10'],
            'attachment_titles.*' => ['required_with:attachments.*', 'string', 'max:255'],
            'attachment_orders' => ['nullable', 'array', 'max:10'],
            'attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:10'],
            'submit_action' => ['required', Rule::in(['draft', 'submit'])],
            'revised_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'resubmitted_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'draft_id' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'remove_existing_files' => ['nullable', 'array'],
            'remove_existing_files.*' => ['integer', Rule::exists('t_document_files', 'id')],
            'existing_attachment_titles' => ['nullable', 'array'],
            'existing_attachment_titles.*' => ['nullable', 'string', 'max:255'],
            'existing_attachment_orders' => ['nullable', 'array'],
            'existing_attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function autosaveValidationRulesForLevel(string $level): array
    {
        $rules = [
            'nama_dokumen' => ['nullable', 'string', 'max:255'],
            'm_proses_bisnis_id' => ['nullable', 'integer', Rule::exists('m_proses_bisnis', 'id')],
            'm_proses_fungsi_id' => ['nullable', 'integer', Rule::exists('m_proses_fungsi', 'id')],
            'reference' => ['nullable', 'string', 'max:255'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')],
            'official_preparer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'nomor_dokumen_suffix' => ['nullable', 'string', 'max:50'],
            'nomor_revisi' => ['nullable', 'string', 'max:20'],
            'tanggal_terbit' => ['nullable', 'date'],
            'catatan_revisi' => ['nullable', 'string', 'max:1000'],
            'revised_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'imported_source' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'resubmitted_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'draft_id' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'imported_document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'filled_template' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'filled_template_word' => ['nullable', 'file', 'mimes:doc,docx', 'max:10240'],
            'revision_content' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'revision_content_word' => ['nullable', 'file', 'mimes:doc,docx', 'max:10240'],
            'revision_form' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'revision_form_word' => ['nullable', 'file', 'mimes:doc,docx', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
            'attachment_titles' => ['nullable', 'array', 'max:10'],
            'attachment_titles.*' => ['nullable', 'string', 'max:255'],
            'attachment_orders' => ['nullable', 'array', 'max:10'],
            'attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:10'],
            'included_attachment_ids' => ['nullable', 'array', 'max:20'],
            'included_attachment_ids.*' => ['integer'],
            'revised_attachments' => ['nullable', 'array', 'max:20'],
            'revised_attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
            'existing_attachment_titles' => ['nullable', 'array'],
            'existing_attachment_titles.*' => ['nullable', 'string', 'max:255'],
            'existing_attachment_orders' => ['nullable', 'array'],
            'existing_attachment_orders.*' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];

        if ($level === 'level-4') {
            $rules['revised_from'] = ['required_without:imported_source', 'nullable', 'integer', Rule::exists('t_document', 'id')];
            $rules['imported_source'] = ['required_without:revised_from', 'nullable', 'integer', Rule::exists('t_document', 'id')];
        }

        if ($level === 'level-1') {
            $rules['nomor_dokumen_suffix'] = $this->manualDocumentNumberSuffixRules(true);
            $rules['nomor_revisi'] = ['nullable', Rule::in(['00.00'])];
        }

        return $rules;
    }

    private function forceInitialRevisionForLevelOne(Request $request, string $level): void
    {
        if ($level !== 'level-1') {
            return;
        }

        $request->merge([
            'nomor_revisi' => '00.00',
        ]);
    }

    private function documentNumberSuffixRules(bool $nullable): array
    {
        return [
            $nullable ? 'nullable' : 'required',
            'string',
            'max:50',
            'regex:/^[A-Za-z0-9]+$/',
        ];
    }

    private function manualDocumentNumberSuffixRules(bool $nullable): array
    {
        return [
            $nullable ? 'nullable' : 'required',
            'string',
            'max:50',
            'regex:/^\d+$/',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function hasAutosavePayload(Request $request, array $validated): bool
    {
        if ($request->allFiles() !== []) {
            return true;
        }

        return collect($validated)
            ->except(['draft_id', 'revised_from'])
            ->filter(function (mixed $value): bool {
                if (is_array($value)) {
                    return collect($value)->filter(fn ($item): bool => filled($item))->isNotEmpty();
                }

                return filled($value);
            })
            ->isNotEmpty();
    }

    private function autosaveRevisionSourceForRequest(Request $request, string $level): ?Document
    {
        if (! filled($request->input('revised_from')) && ! filled($request->input('imported_source'))) {
            return null;
        }

        return $this->revisionSourceForRequest($request, $level);
    }

    private function autosaveDraftForRequest(
        Request $request,
        DocumentLevel $documentLevel,
        ?Document $revisionSource,
        ?string $documentNumber,
        ?Document $resubmissionSource,
    ): ?Document {
        if (filled($request->input('draft_id'))) {
            $draft = Document::query()
                ->with('status')
                ->findOrFail($request->integer('draft_id'));

            $this->authorizeDraftAccess($request, $draft);

            return $draft;
        }

        $query = $this->draftQuery($request)
            ->where('m_document_level_id', $documentLevel->id)
            ->where('resubmitted_from', $resubmissionSource?->id)
            ->where('request_type', $revisionSource !== null ? 'revision' : null)
            ->where('revised_from', $revisionSource?->id);

        if (filled($documentNumber)) {
            $matchingNumberDraft = (clone $query)
                ->where('nomor_dokumen', $documentNumber)
                ->latest('id')
                ->first();

            if ($matchingNumberDraft !== null) {
                return $matchingNumberDraft;
            }
        }

        return (clone $query)
            ->whereNull('nomor_dokumen')
            ->latest('id')
            ->first();
    }

    private function autosaveRevisionNumber(?Document $draft, Document $revisionSource): int
    {
        if ($draft !== null && $draft->revised_from !== null) {
            return $draft->numeric_revision;
        }

        return $this->nextRevisionNumber($revisionSource);
    }

    private function storeAutosaveFiles(Request $request, Document $document): void
    {
        foreach (['imported_document', 'filled_template', 'filled_template_word', 'revision_content', 'revision_content_word', 'revision_form', 'revision_form_word'] as $type) {
            if (! $request->hasFile($type)) {
                continue;
            }

            $this->replaceSingleDocumentFile($document, $type);
            $this->storeDocumentFile($document, $request->file($type), $type, $request->user()->id);
        }

        $this->updateExistingAttachments(
            $document,
            $request->input('existing_attachment_titles', []),
            $request->input('existing_attachment_orders', []),
        );

        $syncSource = $document->revisedFrom;
        if ($syncSource !== null) {
            $this->syncRevisionSourceAttachments(
                $request,
                $document,
                $syncSource,
            );
        }

        $this->storeAttachmentFiles($request, $document);
    }

    private function storeAttachmentFiles(Request $request, Document $document): void
    {
        $titles = collect($request->input('attachment_titles', []))->values();
        $orders = collect($request->input('attachment_orders', []))->values();

        $attachments = collect(array_values($request->file('attachments', [])))
            ->map(function (mixed $attachment, int $index) use ($titles, $orders): array {
                return [
                    'file' => $attachment,
                    'title' => trim((string) ($titles->get($index) ?? '')),
                    'order' => max(1, (int) ($orders->get($index) ?: ($index + 1))),
                    'index' => $index,
                ];
            })
            ->sortBy([
                ['order', 'asc'],
                ['index', 'asc'],
            ]);

        foreach ($attachments as $attachmentData) {
            $attachment = $attachmentData['file'];
            $title = $attachmentData['title'];
            $order = $attachmentData['order'];

            if ($this->hasMatchingAttachment($document, $attachment, $title)) {
                $this->updateMatchingAttachmentOrder($document, $attachment, $title, $order);

                continue;
            }

            $this->storeDocumentFile(
                $document,
                $attachment,
                'attachment',
                $request->user()->id,
                $title !== '' ? $title : null,
                $order,
            );
        }
    }

    private function syncRevisionSourceAttachments(Request $request, Document $document, Document $source): void
    {
        $allSourceAttachments = $source->availableRevisionSourceAttachments();

        if ($allSourceAttachments->isEmpty()) {
            return;
        }

        $includedSourceAttachmentIds = collect($request->input('included_attachment_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($allSourceAttachments as $sourceFile) {
            $replacement = $request->file("revised_attachments.{$sourceFile->id}");
            /** @var DocumentFile|null $existing */
            $existing = $document->files()
                ->where('type_file', 'attachment')
                ->where('source_file_id', $sourceFile->id)
                ->first();

            if (! in_array((int) $sourceFile->id, $includedSourceAttachmentIds, true) && $replacement === null) {
                if ($existing !== null) {
                    $this->deleteDocumentFile($existing);
                }

                continue;
            }

            if ($existing !== null && $replacement === null) {
                $existing->fill([
                    'document_number' => $sourceFile->document_number,
                    'attachment_title' => $sourceFile->attachment_title,
                    'attachment_order' => $sourceFile->attachment_order,
                    'updated_at' => now(),
                ])->save();

                continue;
            }

            if ($existing !== null) {
                $this->deleteDocumentFile($existing);
            }

            if ($replacement !== null) {
                $this->storeDocumentFile(
                    $document,
                    $replacement,
                    'attachment',
                    $request->user()->id,
                    $sourceFile->attachment_title,
                    $sourceFile->attachment_order,
                    $sourceFile->id,
                    $sourceFile->document_number,
                );

                continue;
            }

            if (! Storage::disk('local')->exists($sourceFile->path_file)) {
                continue;
            }

            $path = $this->copyDocumentFileToDocument($sourceFile, $document);

            $document->files()->create([
                'type_file' => 'attachment',
                'document_number' => $sourceFile->document_number,
                'attachment_title' => $sourceFile->attachment_title,
                'attachment_order' => $sourceFile->attachment_order,
                'path_file' => $path,
                'uploaded_by' => $sourceFile->uploaded_by,
                'updated_at' => now(),
                'original_file_name' => $sourceFile->original_file_name,
                'stored_file_name' => basename($path),
                'source_file_id' => $sourceFile->id,
                'file_size' => $sourceFile->file_size,
            ]);
        }
    }

    private function copyDocumentFileToDocument(DocumentFile $sourceFile, Document $targetDocument): string
    {
        $extension = pathinfo($sourceFile->stored_file_name ?: $sourceFile->path_file, PATHINFO_EXTENSION);
        $fileName = Str::random(40).($extension ? '.'.$extension : '');
        $targetPath = "documents/{$targetDocument->id}/{$fileName}";

        Storage::disk('local')->put($targetPath, Storage::disk('local')->get($sourceFile->path_file));

        return $targetPath;
    }

    /**
     * @param  array<int, int|string>  $excludedFileIds
     * @param  array<int|string, string|null>  $attachmentTitles
     * @param  array<int|string, int|string|null>  $attachmentOrders
     */
    private function copyResubmissionSourceFiles(
        Document $source,
        Document $target,
        array $excludedFileIds = [],
        array $attachmentTitles = [],
        array $attachmentOrders = [],
    ): void {
        $excludedFileIds = collect($excludedFileIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->all();

        $source->loadMissing('files');

        foreach ($source->files as $sourceFile) {
            if (in_array((int) $sourceFile->id, $excludedFileIds, true)) {
                continue;
            }

            if (! Storage::disk('local')->exists($sourceFile->path_file)) {
                continue;
            }

            $alreadyCopied = $target->files()
                ->where('source_file_id', $sourceFile->id)
                ->exists();

            if ($alreadyCopied) {
                continue;
            }

            $path = $this->copyDocumentFileToDocument($sourceFile, $target);
            $title = $sourceFile->type_file === 'attachment'
                ? ($attachmentTitles[$sourceFile->id] ?? $sourceFile->attachment_title)
                : $sourceFile->attachment_title;
            $order = $sourceFile->type_file === 'attachment'
                ? ($attachmentOrders[$sourceFile->id] ?? $sourceFile->attachment_order)
                : $sourceFile->attachment_order;

            $target->files()->create([
                'type_file' => $sourceFile->type_file,
                'document_number' => $sourceFile->document_number,
                'attachment_title' => filled($title) ? trim((string) $title) : null,
                'attachment_order' => filled($order) ? max(1, (int) $order) : null,
                'path_file' => $path,
                'uploaded_by' => $sourceFile->uploaded_by,
                'updated_at' => now(),
                'original_file_name' => $sourceFile->original_file_name,
                'stored_file_name' => basename($path),
                'source_file_id' => $sourceFile->id,
                'file_size' => $sourceFile->file_size,
            ]);
        }
    }

    private function updateMatchingAttachmentOrder(Document $document, mixed $file, ?string $title, int $order): void
    {
        $document->files()
            ->where('type_file', 'attachment')
            ->where('original_file_name', $file->getClientOriginalName())
            ->where('file_size', $file->getSize())
            ->where(function ($query) use ($title): void {
                if (filled($title)) {
                    $query->where('attachment_title', $title);

                    return;
                }

                $query->whereNull('attachment_title');
            })
            ->update([
                'attachment_order' => $order,
                'updated_at' => now(),
            ]);
    }

    private function hasMatchingAttachment(Document $document, mixed $file, ?string $title): bool
    {
        return $document->files()
            ->where('type_file', 'attachment')
            ->where('original_file_name', $file->getClientOriginalName())
            ->where('file_size', $file->getSize())
            ->where(function ($query) use ($title): void {
                if (filled($title)) {
                    $query->where('attachment_title', $title);

                    return;
                }

                $query->whereNull('attachment_title');
            })
            ->exists();
    }

    /**
     * @param  array<int|string, string|null>  $titles
     */
    private function updateExistingAttachments(Document $document, array $titles, array $orders = []): void
    {
        if ($titles === [] && $orders === []) {
            return;
        }

        $fileIds = collect(array_keys($titles))
            ->merge(array_keys($orders))
            ->unique()
            ->all();

        foreach ($fileIds as $fileId) {
            $title = $titles[$fileId] ?? null;
            $order = $orders[$fileId] ?? null;

            $document->files()
                ->whereKey($fileId)
                ->where('type_file', 'attachment')
                ->update([
                    'attachment_title' => filled($title) ? trim((string) $title) : null,
                    'attachment_order' => filled($order) ? max(1, (int) $order) : null,
                    'updated_at' => now(),
                ]);
        }
    }

    private function draftQuery(Request $request)
    {
        $draftStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::DRAFT)
            ->value('id');

        return Document::query()
            ->where('user_id', $request->user()->id)
            ->when($draftStatusId, fn ($query) => $query->where('m_status_document_id', $draftStatusId));
    }

    private function draftForRequest(Request $request): ?Document
    {
        if (! filled($request->input('draft_id'))) {
            return null;
        }

        $draft = Document::query()
            ->with(['status', 'documentLevel', 'files', 'departments', 'officialPreparer', 'resubmittedFrom.files', 'resubmittedFrom.status', 'revisedFrom.status', 'revisedFrom.documentLevel', 'revisedFrom.departments'])
            ->findOrFail($request->integer('draft_id'));

        $this->authorizeDraftAccess($request, $draft);

        return $draft;
    }

    private function resubmissionSourceForRequest(Request $request, string $level): ?Document
    {
        $sourceId = $request->input('resubmitted_from') ?: $request->query('resubmitted_from');

        if (! filled($sourceId)) {
            return null;
        }

        $source = Document::query()
            ->with([
                'status',
                'documentLevel',
                'businessProcess',
                'businessFunction',
                'departments',
                'files',
                'officialPreparer',
                'outgoingRelations',
                'revisedFrom.status',
                'revisedFrom.documentLevel',
                'revisedFrom.businessProcess',
                'revisedFrom.businessFunction',
                'revisedFrom.departments',
                'revisedFrom.outgoingRelations',
            ])
            ->findOrFail((int) $sourceId);

        $this->authorizeRejectedResubmissionAccess($request, $source);
        abort_unless($source->documentLevel?->kode === $level, 404);

        if ($source->revised_from !== null) {
            abort_unless($source->revisedFrom?->status?->nama_status === StatusDocument::APPROVED, 404);
        }

        return $source;
    }

    private function acquireDocumentNumberLock(string $documentNumber): bool
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $result = DB::selectOne('select get_lock(?, 10) as acquired', [$this->documentNumberLockName($documentNumber)]);

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw ValidationException::withMessages([
                'nomor_dokumen_suffix' => 'Nomor dokumen sedang diproses. Coba lagi beberapa saat.',
            ]);
        }

        return true;
    }

    private function releaseDocumentNumberLock(string $documentNumber): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::selectOne('select release_lock(?)', [$this->documentNumberLockName($documentNumber)]);
    }

    private function documentNumberLockName(string $documentNumber): string
    {
        return 'doc-num:'.substr(hash('sha256', $documentNumber), 0, 48);
    }

    /**
     * @return Collection<int, Document>
     */
    private function lockedDocumentsForNumber(string $documentNumber): Collection
    {
        return Document::query()
            ->with('status')
            ->where('nomor_dokumen', $documentNumber)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function resubmittedFromIdForReusableNumber(Collection $sameNumberDocuments, ?Document $draft): ?int
    {
        $otherDocuments = $sameNumberDocuments
            ->reject(fn (Document $document): bool => $draft !== null && $document->id === $draft->id)
            ->reject(fn (Document $document): bool => $this->isDraftDocument($document))
            ->values();

        if ($otherDocuments->isEmpty()) {
            return null;
        }

        if ($otherDocuments->contains(fn (Document $document): bool => $this->documentNumberWasEverMaster($document))) {
            throw ValidationException::withMessages([
                'nomor_dokumen_suffix' => 'Nomor dokumen sudah digunakan oleh dokumen master.',
            ]);
        }

        if ($otherDocuments->contains(fn (Document $document): bool => ! $this->isReusableRejectedInitialAttempt($document))) {
            throw ValidationException::withMessages([
                'nomor_dokumen_suffix' => 'Nomor dokumen sudah digunakan.',
            ]);
        }

        return $otherDocuments
            ->sortByDesc(fn (Document $document): string => sprintf(
                '%010d-%010d',
                $document->rejected_at?->timestamp ?? 0,
                $document->id,
            ))
            ->first()
            ?->id;
    }

    private function documentNumberWasEverMaster(Document $document): bool
    {
        return $document->approved_at !== null
            || in_array($document->status?->nama_status, [
                StatusDocument::APPROVED,
                StatusDocument::OBSOLETE,
            ], true);
    }

    private function isDraftDocument(Document $document): bool
    {
        return $document->status?->nama_status === StatusDocument::DRAFT;
    }

    private function assertDocumentNumberAllowedForV2(string $documentNumber): void
    {
        $numberParts = $this->numberParts($documentNumber);

        if ($numberParts === null) {
            return;
        }

        $setup = DocumentNumberingSetup::query()
            ->where('scope_identifier', $numberParts['scope'])
            ->lockForUpdate()
            ->first();

        if ($setup === null) {
            return;
        }

        if ($numberParts['sequence'] < $setup->v2_start_number) {
            throw ValidationException::withMessages([
                'nomor_dokumen_suffix' => "Nomor dokumen berada pada reserved range existing. Mulai gunakan nomor {$setup->v2_start_number} untuk scope {$setup->scope_identifier}.",
            ]);
        }
    }

    private function assertDocumentNumberFollowsRegistrySequence(string $documentNumber): void
    {
        $numberParts = $this->numberParts($documentNumber);

        if ($numberParts === null || ! Schema::hasTable('document_number_registry')) {
            return;
        }

        $latestRegisteredSequence = DocumentNumberRegistry::query()
            ->where('scope_identifier', $numberParts['scope'])
            ->pluck('document_number')
            ->map(fn (string $registeredNumber): ?array => $this->numberParts($registeredNumber))
            ->filter(fn (?array $registeredParts): bool => $registeredParts !== null
                && $registeredParts['scope'] === $numberParts['scope'])
            ->pluck('sequence')
            ->max();

        if ($latestRegisteredSequence === null) {
            return;
        }

        $nextSequence = ((int) $latestRegisteredSequence) + 1;

        if ($numberParts['sequence'] < $nextSequence) {
            throw ValidationException::withMessages([
                'nomor_dokumen_suffix' => "Nomor dokumen berada di bawah nomor terakhir. Gunakan nomor {$nextSequence} atau lebih besar untuk scope {$numberParts['scope']}.",
            ]);
        }
    }

    private function claimTDocumentNumber(Document $document, int $userId): void
    {
        if (! filled($document->nomor_dokumen)) {
            return;
        }

        $registry = DocumentNumberRegistry::query()
            ->where('document_number', $document->nomor_dokumen)
            ->lockForUpdate()
            ->first();

        if (
            $registry !== null
            && $registry->source_type !== DocumentNumberRegistry::SOURCE_T_DOCUMENT
        ) {
            throw ValidationException::withMessages([
                'nomor_dokumen_suffix' => 'Nomor dokumen sudah terdaftar sebagai imported existing.',
            ]);
        }

        DocumentNumberRegistry::updateOrCreate(
            ['document_number' => $document->nomor_dokumen],
            [
                'scope_identifier' => $this->numberParts($document->nomor_dokumen)['scope'] ?? null,
                'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
                'source_id' => $document->id,
                'registered_by' => $userId,
                'registered_at' => now(),
            ],
        );
    }

    /**
     * @return array{scope: string, sequence: int}|null
     */
    private function numberParts(string $documentNumber): ?array
    {
        $segments = collect(explode('-', $documentNumber))
            ->map(fn (string $segment): string => trim($segment))
            ->filter()
            ->values();

        if ($segments->count() < 2) {
            return null;
        }

        $sequence = $segments->pop();

        if (! ctype_digit($sequence)) {
            return null;
        }

        return [
            'scope' => $segments->implode('-'),
            'sequence' => (int) $sequence,
        ];
    }

    private function isReusableRejectedInitialAttempt(Document $document): bool
    {
        return $document->revised_from === null
            && $document->request_type === null
            && $document->approved_at === null
            && $document->status?->nama_status === StatusDocument::REJECTED;
    }

    private function authorizeRejectedResubmissionAccess(Request $request, Document $document): void
    {
        abort_unless($document->status?->nama_status === StatusDocument::REJECTED, 404);
        abort_unless(
            in_array($request->user()->id, array_filter([
                $document->user_id,
                $document->official_preparer_id,
            ]), true),
            403,
        );
    }

    private function documentHasReusableFile(?Document $draft, ?Document $resubmissionSource, string $type, array $excludedFileIds = []): bool
    {
        $excludedFileIds = collect($excludedFileIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->all();

        return $draft?->files()->where('type_file', $type)->exists()
            || $resubmissionSource?->files()
                ->where('type_file', $type)
                ->when($excludedFileIds !== [], fn ($query) => $query->whereNotIn('id', $excludedFileIds))
                ->exists();
    }

    private function authorizeDraftAccess(Request $request, Document $document): void
    {
        $document->loadMissing('status', 'documentLevel');

        abort_unless($document->user_id === $request->user()->id, 403);
        abort_unless($document->status?->nama_status === StatusDocument::DRAFT, 404);
    }

    private function revisionSourceForRequest(Request $request, string $level): ?Document
    {
        $sourceId = $request->input('revised_from')
            ?: $request->query('revised_from')
            ?: $request->input('imported_source')
            ?: $request->query('imported_source');

        if (filled($sourceId)) {
            $source = Document::query()
                ->with(['status', 'documentLevel', 'businessProcess', 'businessFunction', 'departments', 'outgoingRelations', 'revisedFrom.documentLevel'])
                ->whereKey($sourceId)
                ->firstOrFail();

            abort_unless($level === 'level-4' || $source->documentLevel?->kode === $level, 404);
            abort_unless(
                $source->status?->nama_status === StatusDocument::APPROVED,
                404,
            );
            abort_unless($this->userCanRequestRevision($request, $source), 403);

            return $source;
        }

        return null;
    }

    private function userCanRequestRevision(Request $request, Document $document): bool
    {
        $user = $request->user();

        if ($user?->isDeveloper() || $user?->isAdmin()) {
            return true;
        }

        if ($document->origin !== Document::ORIGIN_WORKFLOW && ($user?->hasPermission('documents.existing.imports.revision') ?? false)) {
            return true;
        }

        if ($user?->m_department_id === null) {
            return false;
        }

        if ($document->relationLoaded('departments')) {
            return $document->departments->contains('id', $user->m_department_id);
        }

        return $document->departments()
            ->whereKey($user->m_department_id)
            ->exists();
    }

    private function syncProcedureReference(Document $document, string $level, ?string $reference, ?int $createdBy): void
    {
        if ($level !== 'level-3') {
            $document->outgoingRelations()
                ->where('relation_type', DocumentRelation::REFERENCES)
                ->delete();

            return;
        }

        DocumentRelation::syncDocumentSourceReference($document, $reference, $createdBy);
    }

    protected function referenceRulesForLevel(string $level): array
    {
        if ($level !== 'level-3') {
            return ['nullable', 'string', 'max:255'];
        }

        $procedureReferenceValues = $this->activeProcedureReferences(
            (int) request('m_proses_bisnis_id'),
            (int) request('m_proses_fungsi_id'),
        )->pluck('reference_value')->all();

        return ['required', 'string', Rule::in($procedureReferenceValues)];
    }

    protected function activeProcedureReferences(?int $businessProcessId = null, ?int $businessFunctionId = null): Collection
    {
        $procedureLevelId = DocumentLevel::query()
            ->where('kode', 'level-2')
            ->value('id');

        $approvedStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::APPROVED)
            ->value('id');

        if ($procedureLevelId === null || $approvedStatusId === null) {
            return collect();
        }

        return Document::query()
            ->with(['documentLevel'])
            ->where('m_status_document_id', $approvedStatusId)
            ->where(function ($query) use ($procedureLevelId): void {
                $query
                    ->where('m_document_level_id', $procedureLevelId)
                    ->orWhereNotNull('revised_from');
            })
            ->when($businessProcessId, fn ($query) => $query->where('m_proses_bisnis_id', $businessProcessId))
            ->when($businessFunctionId, fn ($query) => $query->where('m_proses_fungsi_id', $businessFunctionId))
            ->get()
            ->map(function (Document $document): Document {
                $rootDocument = $this->revisionRootDocument($document);

                $document->setRelation('procedureReferenceRoot', $rootDocument);

                return $document;
            })
            ->filter(fn (Document $document): bool => $document
                ->getRelation('procedureReferenceRoot')
                ?->m_document_level_id === $procedureLevelId)
            ->groupBy(fn (Document $document): int => $document
                ->getRelation('procedureReferenceRoot')
                ->id)
            ->map(fn (Collection $family): Document => $family
                ->sortByDesc(fn (Document $document): string => sprintf(
                    '%010d-%010d-%010d',
                    $document->numeric_revision,
                    $document->approved_at?->timestamp ?? 0,
                    $document->id,
                ))
                ->first())
            ->map(function (Document $document): Document {
                $rootDocument = $document->getRelation('procedureReferenceRoot');
                $displayNumber = $rootDocument?->nomor_dokumen ?: $document->nomor_dokumen;

                $document->setAttribute('procedure_reference_number', $displayNumber);
                $document->setAttribute('reference_value', DocumentRelation::referenceValue($document->id));
                $document->setAttribute('reference_source_label', $document->origin === Document::ORIGIN_WORKFLOW ? 'Workflow' : 'Imported');

                return $document;
            })
            ->sortBy('procedure_reference_number')
            ->values();
    }

    private function revisionRootDocument(Document $document): Document
    {
        $root = $document;

        while ($root->revised_from !== null) {
            $parent = Document::query()
                ->select(['id', 'm_document_level_id', 'revised_from', 'nomor_dokumen'])
                ->find($root->revised_from);

            if ($parent === null) {
                break;
            }

            $root = $parent;
        }

        return $root;
    }

    protected function buildDocumentNumber(DocumentLevel $documentLevel, array $validated): ?string
    {
        $suffix = $this->normalizeDocumentNumberSuffix($validated['nomor_dokumen_suffix'] ?? null);

        if (! filled($suffix)) {
            return null;
        }

        $segments = [$documentLevel->prefix];

        if ($documentLevel->kode === 'level-1') {
            $segments[] = $suffix;
        } elseif ($documentLevel->kode === 'level-2') {
            if (! filled($validated['m_proses_fungsi_id'] ?? null)) {
                return null;
            }

            $businessFunctionCode = BusinessFunction::query()
                ->whereKey($validated['m_proses_fungsi_id'])
                ->value('kode');

            if (! filled($businessFunctionCode)) {
                return null;
            }

            $segments[] = $businessFunctionCode;
            $segments[] = $suffix;
        } elseif ($documentLevel->kode === 'level-4') {
            $segments[] = $suffix;
        } elseif ($documentLevel->kode === 'level-3') {
            if (! filled($validated['reference'] ?? null)) {
                return null;
            }

            $segments = collect([$documentLevel->prefix])
                ->merge($this->procedureNumberSegments((string) ($validated['reference'] ?? '')))
                ->push($suffix)
                ->all();
        } else {
            $segments[] = 'XXX';
            $segments[] = 'YY';
            $segments[] = $suffix;
        }

        return collect($segments)
            ->filter()
            ->implode('-');
    }

    private function normalizeDocumentNumberSuffix(mixed $suffix): ?string
    {
        if (! filled($suffix)) {
            return null;
        }

        $suffix = Str::upper(trim((string) $suffix));

        if (ctype_digit($suffix) && strlen($suffix) === 1) {
            return str_pad($suffix, 2, '0', STR_PAD_LEFT);
        }

        return $suffix;
    }

    private function procedureNumberSegments(string $reference): Collection
    {
        $procedureNumber = DocumentRelation::targetDocumentNumber($reference);

        return collect(explode('-', (string) $procedureNumber))
            ->filter()
            ->values()
            ->skip(1)
            ->map(fn (string $segment): string => Str::upper(trim($segment)))
            ->values();
    }

    protected function buildRevisionFormNumber(Document $source, int $revision): string
    {
        $source->nomor_dokumen = $this->revisionSourceMasterNumber($source);

        return (string) app(DocumentFileNumbering::class)->revisionFormNumber($source);
    }

    protected function effectiveRevisionSourceLevelKey(Document $source): ?string
    {
        $source->loadMissing(['documentLevel', 'revisedFrom.documentLevel']);

        if ($source->documentLevel?->kode === 'level-4' && $source->revisedFrom !== null) {
            return $source->revisedFrom->documentLevel?->kode;
        }

        return $source->documentLevel?->kode;
    }

    protected function revisionSourceMasterNumber(Document $source): ?string
    {
        $source->loadMissing(['documentLevel', 'revisedFrom']);

        if ($source->documentLevel?->kode === 'level-4' && $source->revisedFrom !== null) {
            return $source->revisedFrom->nomor_dokumen ?: $source->nomor_dokumen;
        }

        return $source->nomor_dokumen;
    }

    protected function nextRevisionNumber(Document $source): int
    {
        return $source->latestApprovedRevisionNumber() + 1;
    }

    private function hasActiveRevisionRequest(Document $source): bool
    {
        $terminalStatusNames = [
            StatusDocument::APPROVED,
            StatusDocument::OBSOLETE,
            StatusDocument::REJECTED,
            StatusDocument::CANCELLED,
        ];
        $familyIds = $source->revisionFamily()->pluck('id');

        return Document::query()
            ->whereIn('id', $familyIds)
            ->whereNotNull('revised_from')
            ->where(function ($query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', 'revision');
            })
            ->whereHas('status', fn ($query) => $query->whereNotIn('nama_status', $terminalStatusNames))
            ->exists();
    }

    protected function normalizeRevision(?string $revision): int
    {
        if (! filled($revision)) {
            return 0;
        }

        $parts = explode('.', $revision, 2);
        $major = (int) preg_replace('/\D+/', '', $parts[0] ?? '0');
        $minor = (int) preg_replace('/\D+/', '', $parts[1] ?? '0');

        return ($major * 100) + $minor;
    }

    protected function storeDocumentFile(
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
            'document_number' => $documentNumber ?? $this->documentFileNumber($document, $type, $attachmentOrder),
            'attachment_title' => $attachmentTitle,
            'attachment_order' => $attachmentOrder,
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'updated_at' => now(),
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'source_file_id' => $sourceFileId ?? ($type === 'revision_form' ? $this->latestRevisionFormSourceFileId($document) : null),
            'file_size' => $file->getSize(),
        ]);
    }

    private function documentFileNumber(Document $document, string $type, ?int $attachmentOrder = null): ?string
    {
        $document->loadMissing('status');

        if ($document->status?->nama_status === StatusDocument::DRAFT) {
            return null;
        }

        return app(DocumentFileNumbering::class)->numberFor($document, $type, $attachmentOrder);
    }

    private function latestRevisionFormSourceFileId(Document $document): ?int
    {
        if ($document->request_type !== 'revision' || ! filled($document->nomor_dokumen)) {
            return null;
        }

        return DocumentFile::query()
            ->join('t_document', 't_document_files.t_document_id', '=', 't_document.id')
            ->where('t_document.nomor_dokumen', $document->nomor_dokumen)
            ->where('t_document.id', '!=', $document->id)
            ->where('t_document_files.type_file', 'revision_form')
            ->select('t_document_files.id', 't_document.nomor_revisi')
            ->orderByDesc('t_document_files.id')
            ->get()
            ->sortByDesc(fn (object $row): string => sprintf(
                '%010d-%010d',
                Document::normalizeRevisionNumber($row->nomor_revisi),
                $row->id,
            ))
            ->value('id');
    }

    private function replaceSingleDocumentFile(Document $document, string $type): void
    {
        $document->files()
            ->where('type_file', $type)
            ->get()
            ->each(fn (DocumentFile $file) => $this->deleteDocumentFile($file));
    }

    /**
     * @param  array<int, int|string>  $fileIds
     */
    private function removeExistingDocumentFiles(Document $document, array $fileIds): void
    {
        if ($fileIds === []) {
            return;
        }

        $document->files()
            ->whereIn('id', $fileIds)
            ->get()
            ->each(fn (DocumentFile $file) => $this->deleteDocumentFile($file));
    }

    private function deleteDocumentFile(DocumentFile $file): void
    {
        Storage::disk('local')->delete($file->path_file);
        $file->delete();
    }

    private function recordOfficialPreparerApproval(Document $document, int $assignedBy, mixed $respondedAt): void
    {
        if ($document->official_preparer_id === null) {
            return;
        }

        $approvedStatus = ApprovalStatus::findByCode(ApprovalStatus::APPROVED);

        $approval = $document->approvals()->make([
            'm_approval_status_id' => $approvedStatus->id,
            'user_id' => $document->official_preparer_id,
            'role_id' => null,
            'assigned_by' => $assignedBy,
            'assigned_at' => $respondedAt,
            'responded_at' => $respondedAt,
            'stages' => 'TTD Penyusun Resmi',
            'catatan' => 'Tanda tangan penyusun resmi tercatat saat submit dokumen.',
        ]);

        $approval->fillResponseSnapshot()->save();
    }
}
