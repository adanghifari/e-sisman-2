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

class DocumentObsoleteController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => (string) $request->query('type', ''),
            'process' => (string) $request->query('process', ''),
            'origin' => (string) $request->query('origin', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $obsoleteStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::OBSOLETE)
            ->value('id');

        $query = Document::query()
            ->with([
                'status',
                'documentLevel',
                'businessProcess',
                'businessFunction',
                'departments',
                'revisedFrom',
                'files',
            ])
            ->where('m_status_document_id', $obsoleteStatusId)
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
        } elseif ($filters['origin'] === 'imported_current') {
            $query->where('origin', Document::ORIGIN_IMPORTED_CURRENT);
        } elseif ($filters['origin'] === 'imported_legacy') {
            $query->where('origin', Document::ORIGIN_IMPORTED_LEGACY);
        }

        $obsoleteDocuments = $query->get();

        $documents = $this->buildObsoleteFamilyGroups(
            $obsoleteDocuments,
            $filters['sort']
        );

        $totalDocuments = $obsoleteDocuments->count();

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
            'imported_current' => 'Import Ketentuan Saat Ini',
            'imported_legacy' => 'Import Ketentuan Lama',
        ];

        return view('document-management.obsolete.index', [
            'documents' => $documents,
            'totalDocuments' => $totalDocuments,
            'filters' => $filters,
            'typeOptions' => $typeOptions,
            'processOptions' => $processOptions,
            'originOptions' => $originOptions,
            'canCreateObsolete' => $request->user()?->hasPermission('documents.obsolete.create') ?? false,
            'canViewImportedExisting' => $request->user()?->hasPermission('documents.existing.imports.view') ?? false,
            'canCreateImportedExisting' => $request->user()?->hasPermission('documents.obsolete.imports.create') ?? false,
            'canEditImportedExisting' => $request->user()?->hasPermission('documents.master.imports.edit') ?? false,
            'canDeleteImportedExisting' => $request->user()?->hasPermission('documents.existing.imports.delete') ?? false,
            'sortOptions' => [
                'newest' => 'Terbaru',
                'oldest' => 'Terlama',
                'name_asc' => 'Nama A-Z',
                'name_desc' => 'Nama Z-A',
                'revision_desc' => 'Revisi Tertinggi',
            ],
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

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($document->request_type !== 'obsolete', 404);

        $isWorkflow = $document->origin === Document::ORIGIN_WORKFLOW;
        $contentFiles = $document->files->whereIn('type_file', [
            DocumentFile::TYPE_FILLED_TEMPLATE,
            DocumentFile::TYPE_IMPORTED_DOCUMENT,
            DocumentFile::TYPE_REVISION_CONTENT,
        ])->values();
        $primaryContentFile = $contentFiles->first();

        return view('document-management.obsolete.show', [
            'document' => $document,
            'masterDisplayNumber' => $this->masterDisplayNumber($document),
            'revisionRequestDisplayNumber' => $this->revisionRequestDisplayNumber($document),
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
                ? route('documents.obsolete.files.show', [$document, $primaryContentFile])
                : null,
            'documentHistory' => app(DocumentHistory::class)->forDocument($document),
        ]);
    }

    public function restore(Request $request, Document $document): RedirectResponse
    {
        $document->loadMissing('status');

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($this->canAccessRestoreAction($request, $document), 403);

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
                ->route('documents.obsolete.show', $document)
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
        $this->authorizeObsoleteFileAccess($document, $file);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        $recordDocumentDownload->handle($request, $document, $file, [
            'name' => $document->nama_dokumen,
            'number' => $document->nomor_dokumen,
            'revision' => $document->nomor_revisi,
            'context' => 'obsolete',
        ]);

        return response()->file($path, $this->pdfResponseHeaders($file));
    }

    public function preview(Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeObsoleteFileAccess($document, $file);
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
        $this->authorizeObsoleteGeneratedPreviewAccess($document);

        $context = PdfDocumentContext::finalFor($document);
        $watermarkStamp = null;

        if ($request->boolean('download')) {
            $recordDocumentDownload->handle($request, $document, null, [
                'name' => $document->nama_dokumen,
                'number' => $this->masterDisplayNumber($document),
                'revision' => $document->nomor_revisi,
                'context' => 'obsolete',
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
            $watermarkStamp = DocumentWatermarkStamp::forObsolete(
                documentNumber: $this->masterDisplayNumber($document),
                revision: $document->formatted_revision,
                obsoleteAt: $document->obsolete_at ?? $document->updated_at,
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

    private function authorizeObsoleteFileAccess(Document $document, DocumentFile $file): void
    {
        $document->loadMissing('status');

        abort_unless($file->t_document_id === $document->id, 404);
        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($document->request_type !== 'obsolete', 404);
    }

    private function authorizeObsoleteGeneratedPreviewAccess(Document $document): void
    {
        $document->loadMissing('status');

        abort_unless($document->status?->nama_status === StatusDocument::OBSOLETE, 404);
        abort_unless($document->request_type !== 'obsolete', 404);
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

    private function canRestoreMaster(Request $request, Document $document): bool
    {
        if (! $this->canAccessRestoreAction($request, $document)) {
            return false;
        }

        if ($this->activeMasterInFamily($document) !== null) {
            return false;
        }

        return $request->user()?->hasPermission('documents.obsolete.restore') ?? false;
    }

    private function canAccessRestoreAction(Request $request, Document $document): bool
    {
        if ($document->status?->nama_status !== StatusDocument::OBSOLETE) {
            return false;
        }

        return $request->user()?->hasPermission('documents.obsolete.restore') ?? false;
    }

    private function activeMasterInFamily(Document $document): ?Document
    {
        $approvedStatus = StatusDocument::findByName(StatusDocument::APPROVED);

        return $document->revisionFamily()
            ->first(fn (Document $revision): bool => $revision->id !== $document->id
                && $revision->m_status_document_id === $approvedStatus->id
                && $this->isVisibleMasterRecord($revision));
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

    private function buildObsoleteFamilyGroups(
        Collection $obsoleteDocuments,
        string $sort,
    ): Collection {
        if ($obsoleteDocuments->isEmpty()) {
            return collect();
        }

        $presented = $obsoleteDocuments->mapWithKeys(function (Document $doc): array {
            return ["doc:{$doc->id}" => $this->presentObsoleteItem($doc)];
        });

        $adj = [];
        $addNode = function (string $u) use (&$adj): void {
            if (! isset($adj[$u])) {
                $adj[$u] = [];
            }
        };
        $addEdge = function (string $u, string $v) use (&$adj, $addNode): void {
            $addNode($u);
            $addNode($v);
            $adj[$u][] = $v;
            $adj[$v][] = $u;
        };

        foreach ($presented->keys() as $key) {
            $addNode($key);
        }

        foreach ($obsoleteDocuments as $doc) {
            $node = "doc:{$doc->id}";

            // 1. revised_from root
            $rootId = $doc->revisionRootId();
            $addEdge($node, "root:{$rootId}");

            // 2. Fallback: nomor_dokumen
            $docNum = Str::upper(trim((string) $doc->nomor_dokumen));
            if ($docNum !== '' && $docNum !== '-') {
                $addEdge($node, "docnum:{$docNum}");
            }
        }

        // 3. DocumentRelation superseded_by
        $docIds = $obsoleteDocuments->pluck('id')->all();
        if (! empty($docIds)) {
            $relations = DocumentRelation::query()
                ->where('relation_type', DocumentRelation::SUPERSEDED_BY)
                ->where(function ($q) use ($docIds): void {
                    $q->whereIn('source_document_id', $docIds)
                        ->orWhereIn('target_document_id', $docIds);
                })
                ->get();

            foreach ($relations as $rel) {
                $src = "doc:{$rel->source_document_id}";
                $tgt = "doc:{$rel->target_document_id}";
                $addEdge($src, $tgt);
            }
        }

        $visited = [];
        $families = collect();

        foreach ($adj as $node => $neighbors) {
            if (! str_starts_with($node, 'doc:')) {
                continue;
            }
            if (isset($visited[$node])) {
                continue;
            }

            $queue = [$node];
            $visited[$node] = true;
            $componentDocs = [];

            while (! empty($queue)) {
                $curr = array_shift($queue);
                if (str_starts_with($curr, 'doc:') && isset($presented[$curr])) {
                    $componentDocs[$curr] = $presented[$curr];
                }

                foreach ($adj[$curr] ?? [] as $neighbor) {
                    if (! isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }

            if (! empty($componentDocs)) {
                $familyCollection = collect(array_values($componentDocs));

                $sortedFamily = $familyCollection
                    ->sortByDesc(fn (object $item): string => sprintf(
                        '%010d-%010d-%010d-%010d',
                        $item->numeric_revision,
                        $item->tanggal_obsolete?->timestamp ?? 0,
                        $item->tanggal_terbit?->timestamp ?? 0,
                        $item->source_id,
                    ))
                    ->values();

                $parent = clone $sortedFamily->first();

                // Clean master base document number if parent is a revision form starting with FM
                $baseNumber = $familyCollection
                    ->map(fn (object $i): string => (string) ($i->obsolete_display_number ?: $i->nomor_dokumen))
                    ->first(fn (string $num): bool => ! Str::startsWith($num, ['FM', 'fm']) && $num !== '' && $num !== '-');

                if ($baseNumber && Str::startsWith((string) $parent->nomor_dokumen, ['FM', 'fm'])) {
                    $parent->nomor_dokumen = $baseNumber;
                    $parent->obsolete_display_number = $baseNumber;
                }

                $parent->child_documents = $sortedFamily->slice(1)->values();
                $parent->obsoleteChildDocuments = $parent->child_documents;
                $families->push($parent);
            }
        }

        return $this->sortPresentedObsoleteRows($families, $sort);
    }

    private function presentObsoleteItem(Document $doc): object
    {
        $rootDocument = $doc->revised_from !== null
            ? Document::query()->whereKey($doc->revisionRootId())->first()
            : null;

        $masterNumber = $rootDocument?->nomor_dokumen ?: $doc->nomor_dokumen ?: '-';
        $publishedAt = $doc->tanggal_terbit ?? $doc->approved_at;
        $isImported = $doc->origin !== Document::ORIGIN_WORKFLOW;

        return (object) [
            'source_type' => $isImported ? 'imported' : 'workflow',
            'source_id' => $doc->id,
            'source' => $doc,
            'id' => $doc->id,
            'is_imported' => $isImported,
            'nama_dokumen' => $doc->nama_dokumen,
            'nomor_dokumen' => $masterNumber,
            'obsolete_display_number' => $masterNumber,
            'nomor_revisi' => $doc->formatted_revision,
            'formatted_revision' => $doc->formatted_revision,
            'numeric_revision' => $doc->numeric_revision,
            'department' => $doc->departments->pluck('nama_department')->implode(', ') ?: 'Tanpa department',
            'departments' => $doc->departments,
            'proses_bisnis' => $doc->businessProcess?->nama_proses_bisnis,
            'proses_fungsi' => $doc->businessFunction?->nama_proses_fungsi,
            'businessProcess' => $doc->businessProcess,
            'businessFunction' => $doc->businessFunction,
            'tanggal_terbit' => $publishedAt,
            'approved_at' => $doc->approved_at,
            'tanggal_obsolete' => $doc->obsolete_at,
            'detail_url' => route('documents.obsolete.show', $doc),
            'child_documents' => collect(),
            'obsoleteChildDocuments' => collect(),
        ];
    }

    private function sortPresentedObsoleteRows(Collection $rows, string $sort): Collection
    {
        $sortedRows = match ($sort) {
            'oldest' => $rows->sortBy(fn (object $row): string => sprintf(
                '%010d-%010d',
                $row->tanggal_obsolete?->timestamp ?? $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
            'name_asc' => $rows->sortBy(fn (object $row): string => $row->nama_dokumen.'-'.$row->nomor_dokumen),
            'name_desc' => $rows->sortByDesc(fn (object $row): string => $row->nama_dokumen.'-'.$row->nomor_dokumen),
            'revision_desc' => $rows->sortByDesc(fn (object $row): string => sprintf(
                '%010d-%010d-%010d',
                $row->numeric_revision,
                $row->tanggal_obsolete?->timestamp ?? $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
            default => $rows->sortByDesc(fn (object $row): string => sprintf(
                '%010d-%010d',
                $row->tanggal_obsolete?->timestamp ?? $row->tanggal_terbit?->timestamp ?? 0,
                $row->source_id,
            )),
        };

        return $sortedRows->values();
    }
}
