<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Actions\Log\RecordDocumentDownload;
use App\Http\Controllers\Controller;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentDownloadLog;
use App\Models\DocumentFile;
use App\Models\DocumentFinalArtifact;
use App\Models\DocumentLevel;
use App\Models\DocumentRelation;
use App\Models\StatusDocument;
use App\Support\DocumentHistory;
use App\Support\FinalDocuments\DocumentWatermarkStamp;
use App\Support\FinalDocuments\DynamicFinalDocumentRenderer;
use App\Support\FinalDocuments\PdfDocumentContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentMasterController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => (string) $request->query('type', ''),
            'process' => (string) $request->query('process', ''),
            'stamp' => (string) $request->query('stamp', ''),
            'origin' => (string) $request->query('origin', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $approvedStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::APPROVED)
            ->value('id');

        $query = Document::query()
            ->with([
                'status',
                'documentLevel',
                'documentType',
                'businessProcess',
                'businessFunction',
                'creator',
                'officialPreparer',
                'departments',
                'files',
                'revisedFrom.status',
                'revisedFrom.documentLevel',
                'revisedFrom.businessProcess',
                'revisedFrom.businessFunction',
                'revisedFrom.departments',
                'obsoleteRevisions.status',
                'obsoleteRevisions.documentLevel',
                'obsoleteRevisions.businessProcess',
                'obsoleteRevisions.businessFunction',
                'obsoleteRevisions.departments',
            ])
            ->where('m_status_document_id', $approvedStatusId)
            ->where(fn ($query) => $this->whereVisibleMasterRecord($query));

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($query) use ($search): void {
                $query
                    ->where('nama_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                    ->orWhereHas('documentLevel', fn ($query) => $query->where('nama_dokumen', 'like', "%{$search}%"))
                    ->orWhereHas('businessProcess', fn ($query) => $query->where('nama_proses_bisnis', 'like', "%{$search}%"))
                    ->orWhereHas('businessFunction', fn ($query) => $query->where('nama_proses_fungsi', 'like', "%{$search}%"))
                    ->orWhereHas('departments', fn ($query) => $query->where('nama_department', 'like', "%{$search}%"));
            });
        }

        if ($filters['type'] !== '') {
            $query->where('m_document_level_id', $filters['type']);
        }

        if ($filters['process'] !== '') {
            $query->where('m_proses_bisnis_id', $filters['process']);
        }

        if ($filters['origin'] === 'workflow') {
            $query->where('origin', Document::ORIGIN_WORKFLOW);
        } elseif ($filters['origin'] === 'imported') {
            $query->where('origin', '!=', Document::ORIGIN_WORKFLOW);
        }

        match ($filters['sort']) {
            'oldest' => $query->orderBy('approved_at')->orderBy('tanggal_terbit')->orderBy('id'),
            'name_asc' => $query->orderBy('nama_dokumen')->orderBy('nomor_dokumen'),
            'name_desc' => $query->orderByDesc('nama_dokumen')->orderByDesc('nomor_dokumen'),
            'revision_desc' => $query->orderByDesc('approved_at')->orderByDesc('tanggal_terbit')->orderByDesc('id'),
            default => $query->orderByDesc('approved_at')->orderByDesc('tanggal_terbit')->orderByDesc('id'),
        };

        $masterRows = $query->get()
            ->groupBy(fn (Document $document): int => $document->revisionRootId())
            ->map(fn ($family): Document => $family
                ->sortByDesc(fn (Document $document): string => sprintf(
                    '%010d-%010d-%010d',
                    $document->numeric_revision,
                    $document->approved_at?->timestamp ?? $document->tanggal_terbit?->timestamp ?? 0,
                    $document->id,
                ))
                ->first())
            ->values()
            ->map(fn (Document $document) => $this->presentMasterRow($request, $document));

        $documents = $this->sortPresentedMasterRows(
            $masterRows,
            $filters['sort'],
        );

        $typeOptions = ['' => 'Semua Level'] + DocumentLevel::query()
            ->orderBy('id')
            ->pluck('nama_dokumen', 'id')
            ->all();

        $processOptions = ['' => 'Semua Proses'] + BusinessProcess::query()
            ->orderBy('nama_proses_bisnis')
            ->pluck('nama_proses_bisnis', 'id')
            ->all();

        $originOptions = [
            '' => 'Semua Asal Dokumen',
            'workflow' => 'Workflow',
            'imported' => 'Import Master',
        ];

        return view('document-management.master.index', [
            'documents' => $documents,
            'totalDocuments' => $documents->count(),
            'filters' => $filters,
            'typeOptions' => $typeOptions,
            'processOptions' => $processOptions,
            'originOptions' => $originOptions,
            'stampOptions' => [
                '' => 'Semua Stamp',
                StatusDocument::APPROVED => 'Master',
            ],
            'sortOptions' => [
                'newest' => 'Terbaru',
                'oldest' => 'Terlama',
                'name_asc' => 'Nama A-Z',
                'name_desc' => 'Nama Z-A',
                'revision_desc' => 'Revisi Tertinggi',
            ],
            'canImportMaster' => $request->user()?->hasPermission('documents.master.imports.create') ?? false,
            'canEditImportedExisting' => $request->user()?->hasPermission('documents.master.imports.edit') ?? false,
            'canDeleteImportedExisting' => $request->user()?->hasPermission('documents.existing.imports.delete') ?? false,
        ]);
    }

    public function show(Request $request, Document $document): View
    {
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
            'documentLevel.approvalFlows.stages',
            'revisedFrom.status',
        ]);

        abort_unless(
            $document->status?->nama_status === StatusDocument::APPROVED,
            404,
        );
        abort_unless($this->isVisibleMasterRecord($document), 404);

        $isWorkflow = $document->origin === Document::ORIGIN_WORKFLOW;
        $contentFiles = $document->files->whereIn('type_file', [
            DocumentFile::TYPE_FILLED_TEMPLATE,
            DocumentFile::TYPE_IMPORTED_DOCUMENT,
            DocumentFile::TYPE_REVISION_CONTENT,
        ])->values();
        $primaryContentFile = $contentFiles->first();

        return view('document-management.master.show', [
            'document' => $document,
            'masterDisplayNumber' => $this->masterDisplayNumber($document),
            'revisionRequestDisplayNumber' => $this->revisionRequestDisplayNumber($document),
            'canEdit' => (! $isWorkflow) && ($request->user()?->isAdmin() ?? false),
            'canRequestRevision' => $this->canRequestRevision($request, $document),
            'hasActiveRevisionRequest' => $this->hasActiveRevisionRequest($document),
            'canRequestObsolete' => $this->canRequestObsolete($request, $document),
            'canDeleteImportedExisting' => (! $isWorkflow) && ($request->user()?->hasPermission('documents.existing.imports.delete') ?? false),
            'canDirectObsoleteImported' => $this->canRequestImportedObsolete($request, $document),
            'canRestoreMaster' => $this->canRestoreMaster($request, $document),
            'approvalFlowStages' => $document->documentLevel
                ?->approvalFlows
                ->flatMap(fn ($flow) => $flow->stages)
                ->sortBy('stage_order')
                ->values()
                ?? collect(),
            'contentFiles' => $contentFiles,
            'primaryContentFile' => $primaryContentFile,
            'attachmentFiles' => $document->files
                ->whereIn('type_file', [DocumentFile::TYPE_ATTACHMENT, DocumentFile::TYPE_REVISION_FORM])
                ->sortBy(fn (DocumentFile $file): string => $file->type_file === DocumentFile::TYPE_REVISION_FORM
                    ? sprintf('%010d-%010d-%010d', 0, 0, $file->id)
                    : $file->attachmentSortKey())
                ->values(),
            'generatedPrintout' => $this->latestGeneratedPrintout($document),
            'canPreviewGeneratedPrintout' => $isWorkflow && app(DynamicFinalDocumentRenderer::class)
                ->canRender($document, PdfDocumentContext::finalFor($document)),
            'downloadPrintoutUrl' => (! $isWorkflow && $primaryContentFile)
                ? route('documents.master.files.show', [$document, $primaryContentFile])
                : null,
            'documentHistory' => app(DocumentHistory::class)->forDocument($document),
            'relatedObsoleteDocuments' => $this->relatedObsoleteDocumentsForMaster($document),
            'importNote' => (! $isWorkflow) ? $this->importedMasterNote($document) : null,
        ]);
    }

    public function showImported(Request $request, Document $document): View
    {
        return $this->show($request, $document);
    }

    public function obsoleteImported(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('files', 'status');

        abort_unless($document->isMaster() && $document->origin !== Document::ORIGIN_WORKFLOW, 404);
        abort_unless($this->canRequestImportedObsolete($request, $document), 403);

        $validated = $request->validate([
            'catatan_obsolete' => ['required', 'string', 'max:2000'],
        ]);

        $obsoleteStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::OBSOLETE)
            ->value('id');

        DB::transaction(function () use ($document, $validated, $obsoleteStatusId): void {
            $document->update([
                'm_status_document_id' => $obsoleteStatusId,
                'obsolete_at' => now(),
                'catatan' => $this->appendImportedObsoleteNote(
                    $document->catatan,
                    $validated['catatan_obsolete'],
                ),
            ]);
        });

        return redirect()
            ->route('documents.obsolete.show', $document)
            ->with('status', 'Dokumen master hasil import berhasil diobsolete.');
    }

    public function obsolete(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('status', 'documentLevel', 'departments');

        abort_unless($document->status?->nama_status === StatusDocument::APPROVED, 404);
        abort_unless($this->isVisibleMasterRecord($document), 404);
        abort_unless($this->canRequestObsolete($request, $document), 403);

        $validated = $request->validate([
            'catatan_obsolete' => ['required', 'string', 'max:1000'],
        ]);

        $status = StatusDocument::findByName(StatusDocument::PROPOSED);

        $requestDocument = Document::create([
            'm_document_level_id' => $document->m_document_level_id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $document->m_document_types_id,
            'm_proses_bisnis_id' => $document->m_proses_bisnis_id,
            'm_proses_fungsi_id' => $document->m_proses_fungsi_id,
            'user_id' => $request->user()->id,
            'official_preparer_id' => $document->official_preparer_id ?: $request->user()->id,
            'revised_from' => $document->id,
            'request_type' => 'obsolete',
            'nama_dokumen' => $document->nama_dokumen,
            'nomor_dokumen' => $this->masterDisplayNumber($document),
            'nomor_revisi' => $document->nomor_revisi,
            'catatan_revisi' => $validated['catatan_obsolete'],
            'submitted_at' => now(),
        ]);
        $requestDocument->departments()->sync($document->departments()->pluck('departments.id')->all());

        return redirect()
            ->route('documents.inbox')
            ->with('status', 'Pengajuan obsolete berhasil dikirim.');
    }

    public function restore(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('status', 'departments');

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($this->canRestoreMaster($request, $document), 403);

        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);
        $obsoleteStatus = StatusDocument::findByName(StatusDocument::OBSOLETE);
        $family = $document->revisionFamily();
        $familyIds = $family->pluck('id');
        $activeMaster = $family
            ->first(fn (Document $revision): bool => $revision->id !== $document->id
                && $revision->m_status_document_id === $approvedStatus->id
                && $this->isVisibleMasterRecord($revision));

        if ($activeMaster !== null) {
            return redirect()
                ->route('documents.master.show', $document)
                ->with('restore_warning', [
                    'title' => 'Belum Bisa Dijadikan Master',
                    'message' => $this->restoreBlockedMessage($document, $activeMaster),
                ]);
        }

        DB::transaction(function () use ($document, $familyIds, $approvedStatus, $obsoleteStatus): void {
            $restoredAt = now();

            Document::query()
                ->whereIn('id', $familyIds)
                ->where('id', '!=', $document->id)
                ->where('m_status_document_id', $approvedStatus->id)
                ->where(fn ($query) => $this->whereVisibleMasterRecord($query))
                ->update([
                    'm_status_document_id' => $obsoleteStatus->id,
                    'obsolete_at' => $restoredAt,
                ]);

            $document->update([
                'm_status_document_id' => $approvedStatus->id,
                'approved_at' => $restoredAt,
                'obsolete_at' => null,
            ]);
        });

        return redirect()
            ->route('documents.master.show', $document)
            ->with('status', 'Dokumen berhasil dijadikan master.');
    }

    public function file(Request $request, Document $document, DocumentFile $file, RecordDocumentDownload $recordDocumentDownload): BinaryFileResponse
    {
        $this->authorizeMasterFileAccess($document, $file);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        $recordDocumentDownload->handle($request, $document, $file, [
            'name' => $document->nama_dokumen,
            'number' => $this->masterDisplayNumber($document),
            'revision' => $document->nomor_revisi,
            'context' => 'master',
        ]);

        return response()->file($path, $this->pdfResponseHeaders($file));
    }

    public function preview(Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeMasterFileAccess($document, $file);
        abort_unless(Str::of($file->original_file_name)->lower()->endsWith('.pdf'), 415);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, $this->pdfResponseHeaders($file));
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
        RecordDocumentDownload $recordDocumentDownload,
    ): Response {
        $this->authorizeMasterGeneratedPreviewAccess($document);

        $context = PdfDocumentContext::finalFor($document);
        $watermarkStamp = null;

        if ($request->boolean('download')) {
            $recordDocumentDownload->handle($request, $document, null, [
                'name' => $document->nama_dokumen,
                'number' => $this->masterDisplayNumber($document),
                'revision' => $document->nomor_revisi,
                'context' => 'master',
            ]);

            $downloadCount = DocumentDownloadLog::query()
                ->where('t_document_id', $document->id)
                ->count();

            $watermarkStamp = DocumentWatermarkStamp::forDownload(
                userName: $request->user()?->name ?? 'PENGGUNA',
                downloadTime: now(),
                downloadCount: max(1, $downloadCount),
            );
        } else {
            $watermarkStamp = DocumentWatermarkStamp::forMaster(
                documentNumber: $this->masterDisplayNumber($document),
                revision: $document->formatted_revision,
                publishedAt: $document->tanggal_terbit ?? $document->approved_at,
            );
        }

        $pdf = $renderer->render($document, $context, watermarkStamp: $watermarkStamp);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$renderer->fileName($document, $context).'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function authorizeMasterFileAccess(Document $document, DocumentFile $file): void
    {
        $document->loadMissing('status');

        abort_unless($file->t_document_id === $document->id, 404);
        abort_unless(
            in_array($document->status?->nama_status, [StatusDocument::APPROVED, StatusDocument::OBSOLETE], true),
            404,
        );
        abort_unless($this->isVisibleMasterRecord($document), 404);
    }

    private function authorizeMasterGeneratedPreviewAccess(Document $document): void
    {
        $document->loadMissing('status');

        abort_unless($document->status?->nama_status === StatusDocument::APPROVED, 404);
        abort_unless($this->isVisibleMasterRecord($document), 404);
    }

    private function latestGeneratedPrintout(Document $document): ?DocumentFinalArtifact
    {
        return $document->finalArtifacts
            ->where('artifact_type', DocumentFinalArtifact::TYPE_FINAL_DOCUMENT)
            ->whereIn('generation_status', [
                DocumentFinalArtifact::STATUS_GENERATED,
                DocumentFinalArtifact::STATUS_FAILED,
            ])
            ->sortByDesc('generation_number')
            ->first();
    }

    private function whereVisibleMasterRecord($query): void
    {
        $query
            ->whereNull('request_type')
            ->orWhere('request_type', '!=', 'obsolete');
    }

    private function isVisibleMasterRecord(Document $document): bool
    {
        return $document->request_type !== 'obsolete';
    }

    private function canRequestRevision(Request $request, Document $document): bool
    {
        if ($document->status?->nama_status !== StatusDocument::APPROVED) {
            return false;
        }

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

    private function hasActiveRevisionRequest(Document $document): bool
    {
        return Document::query()
            ->whereIn('id', $document->revisionFamily()->pluck('id'))
            ->whereNotNull('revised_from')
            ->where(function ($query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', 'revision');
            })
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::PROPOSED))
            ->exists();
    }

    private function canRestoreMaster(Request $request, Document $document): bool
    {
        if ($document->status?->nama_status !== StatusDocument::OBSOLETE) {
            return false;
        }

        return $request->user()?->hasPermission('documents.obsolete.restore') ?? false;
    }

    private function canRequestObsolete(Request $request, Document $document): bool
    {
        return $this->canRequestRevision($request, $document);
    }

    private function canRequestImportedObsolete(Request $request, Document $document): bool
    {
        if (! $document->isMaster() || $document->origin === Document::ORIGIN_WORKFLOW) {
            return false;
        }

        return $request->user()?->hasAnyPermission([
            'documents.master.imported.obsolete',
            'documents.obsolete.imports.create',
        ]) ?? false;
    }

    private function appendImportedObsoleteNote(?string $existingNote, string $obsoleteNote): string
    {
        $obsoleteNote = 'Alasan obsolete: '.$obsoleteNote;

        return collect([$existingNote, $obsoleteNote])
            ->filter(fn (?string $note): bool => filled($note))
            ->implode("\n\n");
    }

    private function restoreBlockedMessage(Document $document, Document $activeMaster): string
    {
        $activeVersion = $activeMaster->formatted_revision;

        if ($activeMaster->numeric_revision > $document->numeric_revision) {
            return "Versi terbaru {$activeVersion} masih menjadi master. Silakan obsolete-kan versi terbaru dulu.";
        }

        return "Versi {$activeVersion} masih menjadi master. Silakan obsolete-kan versi {$activeVersion} dulu.";
    }

    private function masterDisplayNumber(Document $document): string
    {
        $rootDocument = Document::query()
            ->whereKey($document->revisionRootId())
            ->first();

        return $rootDocument?->nomor_dokumen ?: $document->nomor_dokumen ?: '-';
    }

    private function revisionRequestDisplayNumber(Document $document): ?string
    {
        if ($document->revised_from === null) {
            return null;
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

    private function revisionFormNumber(Document $document): string
    {
        $prefix = match ($document->documentLevel?->kode) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };
        $segments = collect(explode('-', (string) $document->nomor_dokumen))
            ->filter()
            ->values();

        if ($segments->isNotEmpty()) {
            $segments->shift();
        }

        return collect([$prefix])
            ->merge($segments)
            ->filter()
            ->implode('-');
    }

    private function presentMasterRow(Request $request, Document $document): object
    {
        $rootDocument = Document::query()
            ->whereKey($document->revisionRootId())
            ->first();

        $isImported = $document->origin !== Document::ORIGIN_WORKFLOW;

        return (object) [
            'source_type' => $isImported ? 'imported' : 'workflow',
            'source_id' => $document->id,
            'source' => $document,
            'is_imported' => $isImported,
            'nama_dokumen' => $document->nama_dokumen,
            'nomor_dokumen' => $rootDocument?->nomor_dokumen ?: $document->nomor_dokumen ?: '-',
            'nomor_revisi' => $document->formatted_revision,
            'numeric_revision' => $document->numeric_revision,
            'department' => $document->departments->pluck('nama_department')->implode(', ') ?: 'Tanpa department',
            'proses_bisnis' => $document->businessProcess?->nama_proses_bisnis,
            'proses_fungsi' => $document->businessFunction?->nama_proses_fungsi,
            'tanggal_terbit' => $document->tanggal_terbit ?? $document->approved_at,
            'detail_url' => route('documents.master.show', $document),
            'can_request_revision' => $this->canRequestRevision($request, $document),
            'obsolete_documents' => $this->relatedObsoleteDocumentsForMaster($document),
        ];
    }

    private function sortPresentedMasterRows(Collection $rows, string $sort): Collection
    {
        $sortedRows = match ($sort) {
            'oldest' => $rows->sortBy(fn (object $row): string => sprintf(
                '%010d-%010d',
                $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
            'name_asc' => $rows->sortBy(fn (object $row): string => $row->nama_dokumen.'-'.$row->nomor_dokumen),
            'name_desc' => $rows->sortByDesc(fn (object $row): string => $row->nama_dokumen.'-'.$row->nomor_dokumen),
            'revision_desc' => $rows->sortByDesc(fn (object $row): string => sprintf(
                '%s-%010d-%010d',
                $row->numeric_revision,
                $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
            default => $rows->sortByDesc(fn (object $row): string => sprintf(
                '%010d-%010d',
                $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
        };

        return $sortedRows->values();
    }

    private function relatedObsoleteDocumentsForMaster(Document $master): Collection
    {
        $obsoleteStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::OBSOLETE)
            ->value('id');

        $visitedIds = collect([$master->id]);
        $obsoleteDocuments = collect();

        // 1. Revision Family (revised_from lineage)
        $family = $master->revisionFamily()
            ->loadMissing([
                'status',
                'documentLevel',
                'businessProcess',
                'businessFunction',
                'departments',
            ]);

        foreach ($family as $revision) {
            if ($revision->id !== $master->id
                && $revision->m_status_document_id === $obsoleteStatusId
                && $revision->request_type !== 'obsolete') {
                $visitedIds->push($revision->id);
                $obsoleteDocuments->push($revision);
            }
        }

        // 2. DocumentRelation ('superseded_by' where target is master or any connected obsolete)
        $findSupersededRecursively = function (int $targetId) use (&$findSupersededRecursively, &$visitedIds, &$obsoleteDocuments, $obsoleteStatusId): void {
            $relations = DocumentRelation::query()
                ->with([
                    'sourceDocument.status',
                    'sourceDocument.documentLevel',
                    'sourceDocument.businessProcess',
                    'sourceDocument.businessFunction',
                    'sourceDocument.departments',
                ])
                ->where('relation_type', DocumentRelation::SUPERSEDED_BY)
                ->where('target_document_id', $targetId)
                ->get();

            foreach ($relations as $relation) {
                $source = $relation->sourceDocument;
                if ($source && ! $visitedIds->contains($source->id)) {
                    $visitedIds->push($source->id);
                    if ($source->m_status_document_id === $obsoleteStatusId && $source->request_type !== 'obsolete') {
                        $obsoleteDocuments->push($source);
                    }
                    $findSupersededRecursively($source->id);
                }
            }
        };

        $findSupersededRecursively($master->id);

        foreach ($obsoleteDocuments->pluck('id')->all() as $obsId) {
            $findSupersededRecursively($obsId);
        }

        // 3. Fallback for unlinked legacy data with identical nomor_dokumen
        $docNum = trim((string) $master->nomor_dokumen);
        if ($docNum !== '' && $docNum !== '-') {
            $sameNumberDocs = Document::query()
                ->with(['status', 'documentLevel', 'businessProcess', 'businessFunction', 'departments'])
                ->where('nomor_dokumen', $docNum)
                ->where('id', '!=', $master->id)
                ->where('m_status_document_id', $obsoleteStatusId)
                ->where(fn ($q) => $this->whereVisibleMasterRecord($q))
                ->whereNull('revised_from')
                ->whereDoesntHave('outgoingRelations', fn ($q) => $q->where('relation_type', DocumentRelation::SUPERSEDED_BY))
                ->get();

            foreach ($sameNumberDocs as $sameDoc) {
                if (! $visitedIds->contains($sameDoc->id)) {
                    $visitedIds->push($sameDoc->id);
                    $obsoleteDocuments->push($sameDoc);
                }
            }
        }

        return $obsoleteDocuments
            ->unique('id')
            ->sortByDesc(fn (Document $doc): string => sprintf(
                '%010d-%010d-%010d',
                $doc->numeric_revision,
                $doc->obsolete_at?->timestamp ?? $doc->approved_at?->timestamp ?? $doc->tanggal_terbit?->timestamp ?? 0,
                $doc->id,
            ))
            ->values()
            ->map(fn (Document $doc) => (object) [
                'source_type' => $doc->origin !== Document::ORIGIN_WORKFLOW ? 'imported' : 'workflow',
                'source_id' => $doc->id,
                'source' => $doc,
                'is_imported' => $doc->origin !== Document::ORIGIN_WORKFLOW,
                'nama_dokumen' => $doc->nama_dokumen,
                'nomor_dokumen' => $doc->nomor_dokumen ?: $master->nomor_dokumen ?: '-',
                'nomor_revisi' => $doc->formatted_revision,
                'tanggal_terbit' => $doc->tanggal_terbit ?? $doc->approved_at,
                'tanggal_obsolete' => $doc->obsolete_at,
                'detail_url' => route('documents.obsolete.show', $doc),
            ]);
    }

    private function importedMasterNote(Document $document): string
    {
        return filled($document->catatan)
            ? (string) $document->catatan
            : 'Tidak ada catatan import.';
    }
}
