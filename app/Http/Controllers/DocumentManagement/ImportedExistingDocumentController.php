<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentLevel;
use App\Models\DocumentNumberingSetup;
use App\Models\DocumentNumberRegistry;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportedExistingDocumentController extends Controller
{
    public const STATE_MASTER = 'master';

    public const STATE_OBSOLETE = 'obsolete';

    public const DOCUMENT_STATES = [
        self::STATE_MASTER,
        self::STATE_OBSOLETE,
    ];

    public const CURRENT_RULE = 'current_rule';

    public const LEGACY_RULE = 'legacy_rule';

    public const RULE_TYPES = [
        self::CURRENT_RULE,
        self::LEGACY_RULE,
    ];

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'state' => (string) $request->query('state', ''),
            'rule' => (string) $request->query('rule', ''),
            'process' => (string) $request->query('process', ''),
        ];

        $approvedStatusId = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id');
        $obsoleteStatusId = StatusDocument::query()->where('nama_status', StatusDocument::OBSOLETE)->value('id');

        $query = Document::query()
            ->with(['documentLevel', 'documentType', 'businessProcess', 'businessFunction', 'creator'])
            ->withCount(['files', 'outgoingRelations', 'incomingRelations'])
            ->whereIn('origin', [Document::ORIGIN_IMPORTED_CURRENT, Document::ORIGIN_IMPORTED_LEGACY]);

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($query) use ($search): void {
                $query
                    ->where('nama_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_revisi', 'like', "%{$search}%")
                    ->orWhereHas('documentLevel', fn ($query) => $query->where('nama_dokumen', 'like', "%{$search}%"))
                    ->orWhereHas('businessProcess', fn ($query) => $query->where('nama_proses_bisnis', 'like', "%{$search}%"))
                    ->orWhereHas('businessFunction', fn ($query) => $query->where('nama_proses_fungsi', 'like', "%{$search}%"));
            });
        }

        if ($filters['rule'] !== '') {
            if ($filters['rule'] === self::CURRENT_RULE) {
                $query->where('origin', Document::ORIGIN_IMPORTED_CURRENT);
            } elseif ($filters['rule'] === self::LEGACY_RULE) {
                $query->where('origin', Document::ORIGIN_IMPORTED_LEGACY);
            }
        }

        if ($filters['state'] !== '') {
            if ($filters['state'] === self::STATE_MASTER) {
                $query->where('m_status_document_id', $approvedStatusId);
            } elseif ($filters['state'] === self::STATE_OBSOLETE) {
                $query->where('m_status_document_id', $obsoleteStatusId);
            }
        }

        if ($filters['process'] !== '') {
            $query->where('m_proses_bisnis_id', $filters['process']);
        }

        return view('document-management.existing.imports.index', [
            'documents' => $query
                ->orderBy('m_status_document_id')
                ->orderByDesc('obsolete_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'filters' => $filters,
            'stateOptions' => $this->stateOptions(),
            'ruleOptions' => $this->ruleOptions(),
            'processOptions' => ['' => 'Semua Proses'] + BusinessProcess::query()
                ->orderBy('nama_proses_bisnis')
                ->pluck('nama_proses_bisnis', 'id')
                ->all(),
            'canCreateImportedExisting' => $request->user()?->hasPermission('documents.obsolete.imports.create') ?? false,
            'canDeleteImportedExisting' => $this->canDeleteImportedExisting($request),
        ]);
    }

    public function createMaster(): View
    {
        $documentLevels = collect(config('document-levels', []))
            ->except('level-4')
            ->all();

        return view('document-management.master.imports.index', [
            'documentLevels' => $documentLevels,
        ]);
    }

    public function createMasterLevel(string $level): View
    {
        return $this->createCurrentRuleImportLevel($level, self::STATE_MASTER);
    }

    public function storeMasterLevel(Request $request, string $level): RedirectResponse
    {
        $levelConfig = config('document-levels', [])[$level] ?? null;
        abort_if($levelConfig === null, 404);

        $documentLevelRecord = DocumentLevel::query()->where('kode', $level)->firstOrFail();

        $documentType = $this->documentTypeForLevel($level);

        $request->merge([
            'document_state' => self::STATE_MASTER,
            'obsolete_rule_type' => self::CURRENT_RULE,
            'm_document_level_id' => $documentLevelRecord->id,
            'm_document_types_id' => $documentType?->id,
        ]);

        return $this->storeForState($request);
    }

    public function createObsolete(): View
    {
        $documentLevels = collect(config('document-levels', []))
            ->except('level-4')
            ->all();

        return view('document-management.obsolete.imports.index', [
            'documentLevels' => $documentLevels,
        ]);
    }

    public function createObsoleteLegacy(): View
    {
        return $this->createForState(self::STATE_OBSOLETE, true);
    }

    public function createObsoleteLevel(string $level): View
    {
        return $this->createCurrentRuleImportLevel($level, self::STATE_OBSOLETE);
    }

    private function createForState(string $documentState, bool $legacyOnly = false): View
    {
        return view('document-management.existing.imports.create', [
            'documentState' => $documentState,
            'formAction' => route('documents.obsolete.imports.store'),
            'cancelUrl' => $legacyOnly ? route('documents.obsolete.imports.create') : route('documents.existing.imports.index'),
            'legacyOnly' => $legacyOnly,
            'ruleOptions' => $this->ruleOptions(),
            'documentLevelOptions' => ['' => 'Tidak dipetakan'] + DocumentLevel::query()->orderBy('id')->pluck('nama_dokumen', 'id')->all(),
            'documentTypeOptions' => ['' => 'Tidak dipetakan'] + DocumentType::query()->orderBy('nama_types')->pluck('nama_types', 'id')->all(),
            'processOptions' => ['' => 'Tidak dipetakan'] + BusinessProcess::query()->orderBy('nama_proses_bisnis')->pluck('nama_proses_bisnis', 'id')->all(),
            'functionOptions' => ['' => 'Tidak dipetakan'] + BusinessFunction::query()->orderBy('nama_proses_fungsi')->pluck('nama_proses_fungsi', 'id')->all(),
            'relationDocumentOptions' => $this->relationDocumentOptions(),
            'relationTypeOptions' => $this->relationTypeOptions(),
        ]);
    }

    public function storeNumberingSetup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope_identifier' => ['required', 'string', 'max:255'],
            'existing_start_number' => ['required', 'integer', 'min:0'],
            'existing_end_number' => ['required', 'integer', 'min:0', 'gte:existing_start_number'],
            'v2_start_number' => ['required', 'integer', 'min:0', 'gt:existing_end_number'],
        ]);

        DocumentNumberingSetup::updateOrCreate(
            ['scope_identifier' => $validated['scope_identifier']],
            [
                'existing_start_number' => $validated['existing_start_number'],
                'existing_end_number' => $validated['existing_end_number'],
                'v2_start_number' => $validated['v2_start_number'],
                'configured_by' => $request->user()->id,
                'configured_at' => now(),
            ],
        );

        return back()->with('status', 'Setup numbering dokumen existing berhasil disimpan.');
    }

    public function storeMaster(Request $request): RedirectResponse
    {
        $request->merge([
            'document_state' => self::STATE_MASTER,
            'obsolete_rule_type' => self::CURRENT_RULE,
        ]);

        return $this->storeForState($request);
    }

    public function storeObsolete(Request $request): RedirectResponse
    {
        $request->merge([
            'document_state' => self::STATE_OBSOLETE,
            'obsolete_rule_type' => $request->input('obsolete_rule_type', self::LEGACY_RULE),
        ]);

        return $this->storeForState($request);
    }

    public function storeObsoleteLevel(Request $request, string $level): RedirectResponse
    {
        $levelConfig = config('document-levels', [])[$level] ?? null;
        abort_if($levelConfig === null, 404);

        $documentLevelRecord = DocumentLevel::query()->where('kode', $level)->firstOrFail();
        $documentType = $this->documentTypeForLevel($level);

        $request->merge([
            'document_state' => self::STATE_OBSOLETE,
            'obsolete_rule_type' => self::CURRENT_RULE,
            'm_document_level_id' => $documentLevelRecord->id,
            'm_document_types_id' => $documentType?->id,
        ]);

        return $this->storeForState($request);
    }

    private function createCurrentRuleImportLevel(string $level, string $documentState): View
    {
        $levelConfig = config('document-levels', [])[$level] ?? null;
        abort_if($levelConfig === null, 404);

        $documentLevelRecord = DocumentLevel::query()->where('kode', $level)->first();

        $businessProcesses = BusinessProcess::query()->active()->orderBy('nama_proses_bisnis')->get();
        $businessFunctions = BusinessFunction::query()->active()->orderBy('nama_proses_fungsi')->get();
        $departments = Department::query()->active()->orderBy('nama_department')->get();

        $workflowProcedures = collect();
        $importedProcedures = collect();
        if ($level === 'level-3') {
            $procedureLevelId = DocumentLevel::query()->where('kode', 'level-2')->value('id');
            $approvedStatusId = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id');
            if ($procedureLevelId && $approvedStatusId) {
                $workflowProcedures = Document::query()
                    ->select(['id', 'nomor_dokumen', 'nama_dokumen', 'm_proses_bisnis_id', 'm_proses_fungsi_id'])
                    ->where('m_document_level_id', $procedureLevelId)
                    ->where('m_status_document_id', $approvedStatusId)
                    ->where('origin', Document::ORIGIN_WORKFLOW)
                    ->orderBy('nomor_dokumen')
                    ->get();

                $importedProcedures = Document::query()
                    ->select(['id', 'nomor_dokumen', 'nama_dokumen', 'm_proses_bisnis_id', 'm_proses_fungsi_id'])
                    ->where('m_document_level_id', $procedureLevelId)
                    ->where('m_status_document_id', $approvedStatusId)
                    ->whereIn('origin', [Document::ORIGIN_IMPORTED_CURRENT, Document::ORIGIN_IMPORTED_LEGACY])
                    ->orderBy('nomor_dokumen')
                    ->get();
            }
        }

        $processOptions = ['' => 'Pilih Proses Bisnis'] + $businessProcesses->pluck('nama_proses_bisnis', 'id')->all();
        $functionOptions = ['' => 'Pilih Proses Fungsi'] + $businessFunctions->pluck('nama_proses_fungsi', 'id')->all();
        $departmentOptions = $departments->map(fn ($d) => [
            'value' => $d->id,
            'label' => ($d->kode_department ? $d->kode_department.' - ' : '').$d->nama_department,
        ])->values();
        $selectedDepartmentIds = collect(old('department_ids', []))
            ->map(fn ($id): string => (string) $id)
            ->all();

        $procedureReferenceSegments = function ($procedure): array {
            return collect(explode('-', (string) ($procedure?->nomor_dokumen ?? '')))
                ->map(fn (string $segment): string => Str::upper(trim($segment)))
                ->filter()
                ->values()
                ->skip(1)
                ->values()
                ->all();
        };

        $procedureReferenceNumberSegments = [];
        foreach ($importedProcedures as $proc) {
            $procedureReferenceNumberSegments["imported-{$proc->id}"] = $procedureReferenceSegments($proc);
            $procedureReferenceNumberSegments["existing-{$proc->id}"] = $procedureReferenceSegments($proc);
            $procedureReferenceNumberSegments[(string) $proc->id] = $procedureReferenceSegments($proc);
        }
        foreach ($workflowProcedures as $proc) {
            $procedureReferenceNumberSegments["existing-{$proc->id}"] = $procedureReferenceSegments($proc);
            $procedureReferenceNumberSegments["imported-{$proc->id}"] = $procedureReferenceSegments($proc);
            $procedureReferenceNumberSegments[(string) $proc->id] = $procedureReferenceSegments($proc);
        }

        return view('document-management.master.imports.create', [
            'level' => $level,
            'levelConfig' => $levelConfig,
            'documentLevelRecord' => $documentLevelRecord,
            'documentState' => $documentState,
            'processOptions' => $processOptions,
            'functionOptions' => $functionOptions,
            'businessFunctions' => $businessFunctions,
            'departmentOptions' => $departmentOptions,
            'selectedDepartmentIds' => $selectedDepartmentIds,
            'workflowProcedures' => $workflowProcedures,
            'importedProcedures' => $importedProcedures,
            'procedureReferenceNumberSegments' => $procedureReferenceNumberSegments,
            'relationDocumentOptions' => $this->relationDocumentOptions($documentLevelRecord?->id),
            'relationTypeOptions' => $this->relationTypeOptions(),
        ]);
    }

    public function editMaster(Request $request, Document $document): View
    {
        abort_unless($request->user()?->hasPermission('documents.master.imports.edit'), 403);
        abort_unless($document->origin !== Document::ORIGIN_WORKFLOW, 404);

        $document->loadMissing([
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'departments',
            'files',
            'outgoingRelations',
        ]);

        $level = $document->documentLevel?->kode ?? 'level-2';
        $levelConfig = config("document-levels.{$level}");
        abort_if($levelConfig === null, 404);

        $documentLevelRecord = $document->documentLevel;

        $businessProcesses = BusinessProcess::query()->active()->orderBy('nama_proses_bisnis')->get();
        $businessFunctions = BusinessFunction::query()->active()->orderBy('nama_proses_fungsi')->get();
        $departments = Department::query()->active()->orderBy('nama_department')->get();

        $workflowProcedures = collect();
        $importedProcedures = collect();
        if ($level === 'level-3') {
            $procedureLevelId = DocumentLevel::query()->where('kode', 'level-2')->value('id');
            $approvedStatusId = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id');
            if ($procedureLevelId && $approvedStatusId) {
                $workflowProcedures = Document::query()
                    ->select(['id', 'nomor_dokumen', 'nama_dokumen', 'm_proses_bisnis_id', 'm_proses_fungsi_id'])
                    ->where('m_document_level_id', $procedureLevelId)
                    ->where('m_status_document_id', $approvedStatusId)
                    ->where('origin', Document::ORIGIN_WORKFLOW)
                    ->orderBy('nomor_dokumen')
                    ->get();

                $importedProcedures = Document::query()
                    ->select(['id', 'nomor_dokumen', 'nama_dokumen', 'm_proses_bisnis_id', 'm_proses_fungsi_id'])
                    ->where('m_document_level_id', $procedureLevelId)
                    ->where('m_status_document_id', $approvedStatusId)
                    ->whereIn('origin', [Document::ORIGIN_IMPORTED_CURRENT, Document::ORIGIN_IMPORTED_LEGACY])
                    ->where('id', '!=', $document->id)
                    ->orderBy('nomor_dokumen')
                    ->get();
            }
        }

        $processOptions = ['' => 'Pilih Proses Bisnis'] + $businessProcesses->pluck('nama_proses_bisnis', 'id')->all();
        $functionOptions = ['' => 'Pilih Proses Fungsi'] + $businessFunctions->pluck('nama_proses_fungsi', 'id')->all();
        $departmentOptions = $departments->map(fn ($d) => [
            'value' => $d->id,
            'label' => ($d->kode_department ? $d->kode_department.' - ' : '').$d->nama_department,
        ])->values();

        $selectedDepartmentIds = collect(old('department_ids', $document->departments->pluck('id')->all()))
            ->map(fn ($id): string => (string) $id)
            ->all();

        $procedureReferenceSegments = function ($procedure): array {
            return collect(explode('-', (string) ($procedure?->nomor_dokumen ?? '')))
                ->map(fn (string $segment): string => Str::upper(trim($segment)))
                ->filter()
                ->values()
                ->skip(1)
                ->values()
                ->all();
        };

        $procedureReferenceNumberSegments = [];
        foreach ($importedProcedures as $proc) {
            $procedureReferenceNumberSegments["imported-{$proc->id}"] = $procedureReferenceSegments($proc);
            $procedureReferenceNumberSegments["existing-{$proc->id}"] = $procedureReferenceSegments($proc);
            $procedureReferenceNumberSegments[(string) $proc->id] = $procedureReferenceSegments($proc);
        }
        foreach ($workflowProcedures as $proc) {
            $procedureReferenceNumberSegments["existing-{$proc->id}"] = $procedureReferenceSegments($proc);
            $procedureReferenceNumberSegments["imported-{$proc->id}"] = $procedureReferenceSegments($proc);
            $procedureReferenceNumberSegments[(string) $proc->id] = $procedureReferenceSegments($proc);
        }

        $referenceRelation = $document->outgoingRelations
            ->firstWhere('relation_type', DocumentRelation::REFERENCES);
        $selectedReference = old('reference', $referenceRelation ? DocumentRelation::targetReferenceValue($referenceRelation) : null);

        $docSegments = explode('-', (string) $document->nomor_dokumen);
        $existingSuffix = count($docSegments) > 1 ? end($docSegments) : $document->nomor_dokumen;
        $nomorDokumenSuffix = old('nomor_dokumen_suffix', $existingSuffix);

        $currentFile = $document->files
            ->where('type_file', DocumentFile::TYPE_IMPORTED_DOCUMENT)
            ->first();

        return view('document-management.master.imports.edit', [
            'document' => $document,
            'level' => $level,
            'levelConfig' => $levelConfig,
            'documentLevelRecord' => $documentLevelRecord,
            'processOptions' => $processOptions,
            'functionOptions' => $functionOptions,
            'businessFunctions' => $businessFunctions,
            'departmentOptions' => $departmentOptions,
            'selectedDepartmentIds' => $selectedDepartmentIds,
            'workflowProcedures' => $workflowProcedures,
            'importedProcedures' => $importedProcedures,
            'procedureReferenceNumberSegments' => $procedureReferenceNumberSegments,
            'selectedReference' => $selectedReference,
            'nomorDokumenSuffix' => $nomorDokumenSuffix,
            'currentFile' => $currentFile,
        ]);
    }

    public function updateMaster(Request $request, Document $document): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('documents.master.imports.update'), 403);
        abort_unless($document->origin !== Document::ORIGIN_WORKFLOW, 404);

        $level = $document->documentLevel?->kode ?? 'level-2';
        $isLegacyImport = $document->origin === Document::ORIGIN_IMPORTED_LEGACY;

        $validated = $request->validate([
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'm_proses_bisnis_id' => [$isLegacyImport ? 'nullable' : 'required', 'integer', Rule::exists('m_proses_bisnis', 'id')],
            'm_proses_fungsi_id' => [$isLegacyImport || $level === 'level-1' ? 'nullable' : 'required', 'integer', Rule::exists('m_proses_fungsi', 'id')],
            'department_ids' => [$isLegacyImport ? 'nullable' : 'required', 'array', $isLegacyImport ? 'max:50' : 'min:1'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')],
            'reference' => [! $isLegacyImport && $level === 'level-3' ? 'required' : 'nullable', 'string'],
            'nomor_dokumen' => ['nullable', 'string', 'max:100'],
            'nomor_dokumen_suffix' => ['nullable', 'string', 'max:50'],
            'nomor_revisi' => ['nullable', 'string', 'max:50'],
            'tanggal_terbit' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'existing_document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if (
            ! $isLegacyImport
            &&
            filled($validated['nomor_revisi'] ?? null)
            && ! preg_match('/^\d{2}\.\d{2}$/', (string) $validated['nomor_revisi'])
        ) {
            throw ValidationException::withMessages([
                'nomor_revisi' => 'Nomor revisi imported master wajib menggunakan format 00.00.',
            ]);
        }

        $request->merge(['m_document_level_id' => $document->m_document_level_id]);
        $resolvedNomorDokumen = $this->resolveImportedDocumentNumber($request);

        DB::transaction(function () use ($request, $document, $validated, $resolvedNomorDokumen, $level, $isLegacyImport): void {
            $this->updateClaimedImportedDocumentNumber($document, $resolvedNomorDokumen, $request->user()->id);

            $document->update([
                'nama_dokumen' => $validated['nama_dokumen'],
                'm_proses_bisnis_id' => $validated['m_proses_bisnis_id'] ?? null,
                'm_proses_fungsi_id' => $level === 'level-1' ? null : ($validated['m_proses_fungsi_id'] ?? null),
                'nomor_dokumen' => $resolvedNomorDokumen,
                'nomor_revisi' => filled($validated['nomor_revisi'] ?? null)
                    ? ($isLegacyImport ? (string) $validated['nomor_revisi'] : Document::formatRevisionNumber($validated['nomor_revisi']))
                    : $document->nomor_revisi,
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
            ]);

            $document->departments()->sync($validated['department_ids'] ?? []);

            if ($level === 'level-3') {
                if (filled($validated['reference'] ?? null)) {
                    $targetId = DocumentRelation::targetDocumentIdForReference($validated['reference']);
                    if ($targetId && Document::query()->whereKey($targetId)->exists()) {
                        $document->outgoingRelations()->updateOrCreate(
                            ['relation_type' => DocumentRelation::REFERENCES],
                            [
                                'target_document_id' => $targetId,
                                'keterangan' => 'Dokumen Acuan Prosedur',
                                'created_by' => $request->user()->id,
                            ],
                        );
                    }
                } else {
                    $document->outgoingRelations()
                        ->where('relation_type', DocumentRelation::REFERENCES)
                        ->delete();
                }
            }

            if ($request->hasFile('existing_document')) {
                $oldPrimaryFiles = $document->files()
                    ->where('type_file', DocumentFile::TYPE_IMPORTED_DOCUMENT)
                    ->get();

                foreach ($oldPrimaryFiles as $oldFile) {
                    Storage::disk('local')->delete($oldFile->path_file);
                    $oldFile->delete();
                }

                $this->storeImportedFile(
                    $document,
                    $request->file('existing_document'),
                    DocumentFile::TYPE_IMPORTED_DOCUMENT,
                    $request->user()->id,
                );
            }

            $this->rebuildSameNumberImportedRevisionChain($document->refresh(), $request->user()->id);
        });

        $detailRoute = $document->fresh()?->isMaster()
            ? 'documents.master.show'
            : 'documents.existing.imports.show';

        return redirect()
            ->route($detailRoute, $document)
            ->with('status', 'Metadata dokumen berhasil diperbarui.');
    }

    private function documentTypeForLevel(string $level): ?DocumentType
    {
        $typeNames = [
            'level-1' => ['Manual'],
            'level-2' => ['Prosedur'],
            'level-3' => ['IK', 'Instruksi Kerja'],
        ][$level] ?? ['IK', 'Instruksi Kerja'];

        return DocumentType::query()
            ->whereIn('nama_types', $typeNames)
            ->first();
    }

    private function storeForState(Request $request): RedirectResponse
    {
        $validated = $this->validateStoreRequest($request);
        $document = null;

        $isMaster = $validated['document_state'] === self::STATE_MASTER;
        $isCurrentRule = $validated['obsolete_rule_type'] === self::CURRENT_RULE;

        $origin = $isMaster || $isCurrentRule
            ? Document::ORIGIN_IMPORTED_CURRENT
            : Document::ORIGIN_IMPORTED_LEGACY;

        $statusName = $isMaster ? StatusDocument::APPROVED : StatusDocument::OBSOLETE;
        $statusId = StatusDocument::query()
            ->firstOrCreate(['nama_status' => $statusName])
            ->id;
        $allowImportedObsoleteNumberReuse = $isMaster && $request->boolean('confirm_imported_master_number_reuse');

        if (
            $isMaster
            && ! $allowImportedObsoleteNumberReuse
            && filled($validated['nomor_dokumen'] ?? null)
            && $this->reusableImportedObsoleteNumberSources($validated['nomor_dokumen'])->isNotEmpty()
        ) {
            throw ValidationException::withMessages([
                'nomor_dokumen' => 'Nomor dokumen sudah digunakan oleh dokumen obsolete import. Konfirmasi terlebih dahulu jika ingin menjadikan import master ini sebagai pengganti.',
            ]);
        }

        DB::transaction(function () use ($request, $validated, $isMaster, $origin, $statusId, $allowImportedObsoleteNumberReuse, &$document): void {
            $approvedAt = null;
            $obsoleteAt = null;

            if ($isMaster) {
                $approvedAt = filled($validated['tanggal_terbit'] ?? null)
                    ? $validated['tanggal_terbit']
                    : now();
            } else {
                $obsoleteAt = filled($validated['tanggal_obsolete'] ?? null)
                    ? $validated['tanggal_obsolete']
                    : now();
            }

            $document = Document::create([
                'origin' => $origin,
                'm_status_document_id' => $statusId,
                'm_document_level_id' => $validated['m_document_level_id'] ?? null,
                'm_document_types_id' => $validated['m_document_types_id'] ?? null,
                'm_proses_bisnis_id' => $validated['m_proses_bisnis_id'] ?? null,
                'm_proses_fungsi_id' => $validated['m_proses_fungsi_id'] ?? null,
                'user_id' => $request->user()->id,
                'nama_dokumen' => $validated['nama_dokumen'],
                'nomor_dokumen' => $validated['nomor_dokumen'] ?? null,
                'nomor_revisi' => $this->importedRevisionValue($validated),
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'created_at' => now(),
                'approved_at' => $approvedAt,
                'obsolete_at' => $obsoleteAt,
            ]);

            $document->departments()->sync($validated['department_ids'] ?? []);

            $this->validateRelationsAgainstSavedDocument($document, $validated['relations'] ?? []);

            $this->claimImportedDocumentNumber($document, $request->user()->id, $allowImportedObsoleteNumberReuse);

            $mainFile = $request->file('existing_document') ?: $request->file('obsolete_document');
            if ($mainFile) {
                $this->storeImportedFile(
                    $document,
                    $mainFile,
                    DocumentFile::TYPE_IMPORTED_DOCUMENT,
                    $request->user()->id,
                );
            }

            if (! $isMaster) {
                foreach ($request->file('attachments', []) as $attachment) {
                    $this->storeImportedFile(
                        $document,
                        $attachment,
                        DocumentFile::TYPE_ATTACHMENT,
                        $request->user()->id,
                    );
                }
            }

            if (filled($validated['replacement_reference'] ?? null)) {
                $targetId = DocumentRelation::targetDocumentIdForReference($validated['replacement_reference']);
                if ($targetId && Document::query()->whereKey($targetId)->exists()) {
                    $document->outgoingRelations()->updateOrCreate(
                        ['relation_type' => DocumentRelation::SUPERSEDED_BY],
                        [
                            'target_document_id' => $targetId,
                            'keterangan' => 'Digantikan oleh dokumen terkait.',
                            'created_by' => $request->user()->id,
                        ],
                    );
                }
            }

            foreach ($validated['relations'] ?? [] as $relation) {
                $relationRef = $relation['relation_reference'] ?? null;
                $targetId = $relation['related_document_id']
                    ?? ($relationRef ? DocumentRelation::targetDocumentIdForReference($relationRef) : null);

                if ($targetId && Document::query()->whereKey($targetId)->exists()) {
                    $document->outgoingRelations()->updateOrCreate(
                        ['relation_type' => $relation['relation_type']],
                        [
                            'target_document_id' => $targetId,
                            'keterangan' => $relation['keterangan'] ?? null,
                            'created_by' => $request->user()->id,
                        ],
                    );
                }
            }

            if (filled($validated['reference'] ?? null)) {
                $targetId = DocumentRelation::targetDocumentIdForReference($validated['reference']);
                if ($targetId && Document::query()->whereKey($targetId)->exists()) {
                    $document->outgoingRelations()->updateOrCreate(
                        ['relation_type' => DocumentRelation::REFERENCES],
                        [
                            'target_document_id' => $targetId,
                            'keterangan' => 'Dokumen Acuan Prosedur',
                            'created_by' => $request->user()->id,
                        ],
                    );
                }
            }

            if ($isMaster && $allowImportedObsoleteNumberReuse) {
                $this->rebuildSameNumberImportedRevisionChain($document, $request->user()->id);
            }

            if (! $isMaster && filled($document->nomor_dokumen)) {
                $this->rebuildSameNumberImportedRevisionChain($document, $request->user()->id);
            }
        });

        if ($isMaster) {
            return redirect()
                ->route('documents.master')
                ->with('status', 'Dokumen master existing berhasil diimport.');
        }

        return redirect()
            ->route('documents.obsolete')
            ->with('status', 'Arsip dokumen obsolete berhasil disimpan.');
    }

    public function show(Request $request, Document $document): View
    {
        abort_unless($document->origin !== Document::ORIGIN_WORKFLOW, 404);

        $document->load([
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'creator',
            'files.uploader',
            'outgoingRelations.targetDocument.status',
            'outgoingRelations.creator',
            'incomingRelations.sourceDocument.status',
            'incomingRelations.creator',
        ]);

        return view('document-management.existing.imports.show', [
            'document' => $document,
            'ruleOptions' => $this->ruleOptions(),
            'relationTypeOptions' => $this->relationTypeOptions(),
            'canDeleteImportedExisting' => $this->canDeleteImportedExisting($request),
        ]);
    }

    public function numberReuseCheck(Request $request): JsonResponse
    {
        if ($request->input('document_state') !== self::STATE_MASTER) {
            return response()->json([
                'conflict' => false,
                'document_number' => null,
            ]);
        }

        $resolvedNomorDokumen = $this->resolveImportedDocumentNumber($request);
        $documentNumber = $resolvedNomorDokumen ?: trim((string) $request->input('nomor_dokumen', ''));

        if ($documentNumber === '') {
            return response()->json([
                'conflict' => false,
                'document_number' => null,
            ]);
        }

        $conflictingMaster = $this->existingMasterNumberSources($documentNumber);
        if ($conflictingMaster->isNotEmpty()) {
            return response()->json([
                'conflict' => true,
                'blocked' => true,
                'document_number' => $documentNumber,
                'count' => $conflictingMaster->count(),
                'message' => "Nomor dokumen {$documentNumber} sudah digunakan oleh dokumen master. Hapus master imported tersebut terlebih dahulu sebelum import master baru dengan nomor yang sama.",
            ]);
        }

        $conflictingObsolete = $this->reusableImportedObsoleteNumberSources($documentNumber);

        return response()->json([
            'conflict' => $conflictingObsolete->isNotEmpty(),
            'blocked' => false,
            'document_number' => $documentNumber,
            'count' => $conflictingObsolete->count(),
            'message' => $conflictingObsolete->isEmpty()
                ? null
                : "Nomor dokumen {$documentNumber} sudah digunakan oleh {$conflictingObsolete->count()} dokumen obsolete import. Apakah Anda yakin ingin tetap import sebagai master dan menyusun rantai obsolete tersebut sampai ke master ini?",
        ]);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        abort_unless($document->origin !== Document::ORIGIN_WORKFLOW, 404);
        abort_unless($this->canDeleteImportedExisting($request), 403);

        if ($this->hasNonRechainableRevisions($document)) {
            return redirect()
                ->back()
                ->with('delete_warning', [
                    'title' => 'Dokumen belum bisa dihapus',
                    'message' => 'Dokumen imported ini sudah dipakai sebagai sumber pengajuan revisi atau obsolete yang tidak bisa disambungkan otomatis.',
                ]);
        }

        $documentNumber = $document->nomor_dokumen;
        $previousDocumentId = $document->revised_from;
        $userId = $request->user()->id;

        DB::transaction(function () use ($document, $documentNumber, $previousDocumentId, $userId): void {
            $document->loadMissing('files');

            $this->rechainImportedRevisionChildrenBeforeDelete($document, $previousDocumentId);

            DB::table('t_document_download_logs')
                ->where('t_document_id', $document->id)
                ->delete();

            DB::table('t_approval')
                ->where('t_document_id', $document->id)
                ->delete();

            foreach ($document->files as $file) {
                Storage::disk('local')->delete($file->path_file);
                $file->delete();
            }

            $document->departments()->detach();
            $document->outgoingRelations()->delete();
            $document->incomingRelations()->delete();
            $this->releaseImportedDocumentNumber($document);
            $document->delete();

            $anchor = $this->sameNumberImportedChainDocuments($documentNumber)
                ->first();

            if ($anchor !== null) {
                $this->rebuildSameNumberImportedRevisionChain($anchor, $userId);
            }
        });

        return redirect()
            ->route('documents.existing.imports.index')
            ->with('status', 'Dokumen imported berhasil dihapus.');
    }

    private function hasNonRechainableRevisions(Document $document): bool
    {
        return $document->revisions()
            ->where(function ($query) use ($document): void {
                $query
                    ->where('nomor_dokumen', '!=', $document->nomor_dokumen)
                    ->orWhereNull('nomor_dokumen')
                    ->orWhere('origin', Document::ORIGIN_WORKFLOW)
                    ->orWhereNotNull('request_type');
            })
            ->exists();
    }

    private function rechainImportedRevisionChildrenBeforeDelete(Document $document, ?int $previousDocumentId): void
    {
        $document->revisions()
            ->where('nomor_dokumen', $document->nomor_dokumen)
            ->where('origin', '!=', Document::ORIGIN_WORKFLOW)
            ->whereNull('request_type')
            ->update(['revised_from' => $previousDocumentId]);
    }

    public function file(Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeImportedFileAccess($document, $file);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function preview(Document $document, DocumentFile $file): BinaryFileResponse
    {
        $this->authorizeImportedFileAccess($document, $file);
        abort_unless(Str::of($file->original_file_name)->lower()->endsWith('.pdf'), 415);

        $path = Storage::disk('local')->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStoreRequest(Request $request): array
    {
        $request->merge([
            'document_state' => $request->input('document_state', self::STATE_OBSOLETE),
        ]);

        $resolvedNomorDokumen = $this->resolveImportedDocumentNumber($request);
        if ($resolvedNomorDokumen !== null) {
            $request->merge(['nomor_dokumen' => $resolvedNomorDokumen]);
        }

        $validated = $request->validate([
            'document_state' => ['required', Rule::in(self::DOCUMENT_STATES)],
            'obsolete_rule_type' => ['required', Rule::in(self::RULE_TYPES)],
            'm_document_level_id' => ['required_if:obsolete_rule_type,'.self::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_document_levels', 'id')],
            'm_document_types_id' => ['required_if:obsolete_rule_type,'.self::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_document_types', 'id')],
            'm_proses_bisnis_id' => ['required_if:obsolete_rule_type,'.self::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_proses_bisnis', 'id')],
            'm_proses_fungsi_id' => ['required_if:obsolete_rule_type,'.self::CURRENT_RULE, 'nullable', 'integer', Rule::exists('m_proses_fungsi', 'id')],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'nomor_dokumen_suffix' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/'],
            'nomor_revisi' => ['nullable', 'string', 'max:50'],
            'tanggal_terbit' => ['nullable', 'date'],
            'tanggal_obsolete' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')],
            'existing_document' => ['required_without:obsolete_document', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'obsolete_document' => ['required_without:existing_document', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'replacement_reference' => ['nullable', 'string', 'max:255'],
            'relations' => ['nullable', 'array', 'max:2'],
            'relations.*.relation_reference' => ['nullable', 'string', 'max:255'],
            'relations.*.related_document_id' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'relations.*.relation_type' => ['required_with:relations', Rule::in(DocumentRelation::RELATION_TYPES)],
            'relations.*.keterangan' => ['nullable', 'string', 'max:1000'],
            'confirm_imported_master_number_reuse' => ['nullable', 'boolean'],
        ], [
            'nomor_dokumen_suffix.regex' => 'Nomor dokumen suffix hanya boleh berupa angka atau huruf.',
        ]);

        if (($validated['obsolete_rule_type'] ?? null) === self::CURRENT_RULE) {
            $validated['obsolete_rule_type'] = self::CURRENT_RULE;
            $requiredMasterFields = [
                'm_document_level_id',
                'm_document_types_id',
                'm_proses_bisnis_id',
                'm_proses_fungsi_id',
                'nomor_dokumen',
                'nomor_revisi',
                'department_ids',
            ];
            $masterErrors = [];

            $levelRecord = DocumentLevel::query()->find($validated['m_document_level_id'] ?? null);
            if ($levelRecord?->kode === 'level-3' && ! filled($validated['reference'] ?? null)) {
                $masterErrors['reference'] = 'Pilih Dokumen Level II: Prosedur terlebih dahulu.';
            }

            foreach ($requiredMasterFields as $field) {
                if ($field === 'department_ids') {
                    if (count($validated['department_ids'] ?? []) === 0) {
                        $masterErrors[$field] = 'Pilih minimal satu department untuk import sesuai ketentuan saat ini.';
                    }

                    continue;
                }

                if (! filled($validated[$field] ?? null)) {
                    $masterErrors[$field] = 'Field ini wajib diisi untuk import sesuai ketentuan saat ini.';
                    if ($field === 'nomor_dokumen') {
                        $masterErrors['nomor_dokumen_suffix'] = 'Nomor dokumen wajib diisi.';
                    }
                }
            }

            if (
                filled($validated['nomor_revisi'] ?? null)
                && ! preg_match('/^\d{2}\.\d{2}$/', (string) $validated['nomor_revisi'])
            ) {
                $masterErrors['nomor_revisi'] = 'Nomor revisi untuk import sesuai ketentuan saat ini wajib menggunakan format 00.00.';
            }

            if ($masterErrors !== []) {
                throw ValidationException::withMessages($masterErrors);
            }
        }

        if (
            filled($validated['replacement_reference'] ?? null)
            && $this->replacementRelationAttributes($validated['replacement_reference']) === null
        ) {
            throw ValidationException::withMessages([
                'replacement_reference' => 'Pilih dokumen pengganti yang valid.',
            ]);
        }

        if (
            filled($validated['replacement_reference'] ?? null)
            && ($validated['obsolete_rule_type'] ?? null) === self::CURRENT_RULE
            && ! $this->replacementReferenceMatchesCurrentRuleContext($validated)
        ) {
            throw ValidationException::withMessages([
                'replacement_reference' => 'Dokumen pengganti harus memiliki level, proses bisnis, dan proses fungsi yang sama.',
            ]);
        }

        $relationErrors = [];

        foreach ($validated['relations'] ?? [] as $index => $relation) {
            if (filled($relation['relation_reference'] ?? null)) {
                $relationAttributes = $this->relationTargetAttributes(
                    $relation['relation_reference'],
                    $relation['relation_type'] ?? null,
                );

                if ($relationAttributes === null) {
                    $relationErrors["relations.{$index}.relation_reference"] = 'Pilih target dokumen yang valid.';

                    continue;
                }

                $validated['relations'][$index]['related_document_id'] = $relationAttributes['target_document_id'];
            }

            $targetId = $validated['relations'][$index]['related_document_id'] ?? null;
            if (! filled($targetId)) {
                $relationErrors["relations.{$index}.related_document_id"] = 'Pilih target relasi.';
            }
        }

        if ($relationErrors !== []) {
            throw ValidationException::withMessages($relationErrors);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function importedRevisionValue(array $validated): string
    {
        $revision = $validated['nomor_revisi'] ?? null;

        if (($validated['obsolete_rule_type'] ?? null) === self::CURRENT_RULE) {
            return Document::formatRevisionNumber($revision);
        }

        return filled($revision) ? (string) $revision : '00.00';
    }

    /**
     * @param  array<int, array<string, mixed>>  $relations
     */
    private function validateRelationsAgainstSavedDocument(Document $document, array $relations): void
    {
        foreach ($relations as $index => $relation) {
            $targetId = $relation['related_document_id']
                ?? DocumentRelation::targetDocumentIdForReference($relation['relation_reference'] ?? null);

            if ((int) $targetId === (int) $document->id) {
                throw ValidationException::withMessages([
                    "relations.{$index}.related_document_id" => 'Dokumen tidak boleh berelasi ke dirinya sendiri.',
                ]);
            }
        }
    }

    private function storeImportedFile(
        Document $document,
        mixed $file,
        string $type,
        int $uploadedBy,
    ): DocumentFile {
        $path = $file->store("documents/{$document->id}/imported", 'local');

        return $document->files()->create([
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'file_size' => $file->getSize(),
            'updated_at' => now(),
        ]);
    }

    private function authorizeImportedFileAccess(Document $document, DocumentFile $file): void
    {
        abort_unless($document->origin !== Document::ORIGIN_WORKFLOW, 404);
        abort_unless($file->t_document_id === $document->id, 404);
    }

    /**
     * @return Collection<int, Document>
     */
    private function existingMasterNumberSources(?string $documentNumber): Collection
    {
        if (! filled($documentNumber)) {
            return collect();
        }

        $approvedStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::APPROVED)
            ->value('id');

        if ($approvedStatusId === null) {
            return collect();
        }

        return Document::query()
            ->where('nomor_dokumen', $documentNumber)
            ->where('m_status_document_id', $approvedStatusId)
            ->where(function ($query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', '!=', 'obsolete');
            })
            ->get();
    }

    private function claimImportedDocumentNumber(Document $document, int $userId, bool $allowReplaceImportedObsoleteRegistry = false): void
    {
        if (! filled($document->nomor_dokumen)) {
            return;
        }

        $approvedStatusId = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id');
        $isMaster = (int) $document->m_status_document_id === (int) $approvedStatusId;

        if ($isMaster && Document::query()
            ->where('nomor_dokumen', $document->nomor_dokumen)
            ->where('id', '!=', $document->id)
            ->where('m_status_document_id', $approvedStatusId)
            ->exists()) {
            throw ValidationException::withMessages([
                'nomor_dokumen' => 'Nomor dokumen sudah digunakan.',
            ]);
        }

        $existingRegistry = DocumentNumberRegistry::query()
            ->where('document_number', $document->nomor_dokumen)
            ->lockForUpdate()
            ->first();

        if ($existingRegistry !== null) {
            if ($existingRegistry->source_type === DocumentNumberRegistry::SOURCE_T_DOCUMENT
                && (int) $existingRegistry->source_id === (int) $document->id) {
                return;
            }

            if (
                $isMaster
                && $allowReplaceImportedObsoleteRegistry
                && $this->registryBelongsToReusableImportedObsolete($existingRegistry, $document->nomor_dokumen)
            ) {
                $existingRegistry->update([
                    'scope_identifier' => $this->scopeIdentifierForNumber($document->nomor_dokumen),
                    'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
                    'source_id' => $document->id,
                    'registered_by' => $userId,
                    'registered_at' => now(),
                ]);

                return;
            }

            if (! $isMaster && $existingRegistry->source_type === DocumentNumberRegistry::SOURCE_T_DOCUMENT) {
                return;
            }

            throw ValidationException::withMessages([
                'nomor_dokumen' => 'Nomor dokumen sudah digunakan.',
            ]);
        }

        DocumentNumberRegistry::create([
            'document_number' => $document->nomor_dokumen,
            'scope_identifier' => $this->scopeIdentifierForNumber($document->nomor_dokumen),
            'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
            'source_id' => $document->id,
            'registered_by' => $userId,
            'registered_at' => now(),
        ]);
    }

    private function releaseImportedDocumentNumber(Document $document): void
    {
        if (! filled($document->nomor_dokumen)) {
            return;
        }

        DocumentNumberRegistry::query()
            ->where('document_number', $document->nomor_dokumen)
            ->where('source_type', DocumentNumberRegistry::SOURCE_T_DOCUMENT)
            ->where('source_id', $document->id)
            ->delete();
    }

    private function registryBelongsToReusableImportedObsolete(DocumentNumberRegistry $registry, string $documentNumber): bool
    {
        if ($registry->source_type !== DocumentNumberRegistry::SOURCE_T_DOCUMENT || $registry->source_id === null) {
            return false;
        }

        return $this->reusableImportedObsoleteNumberSources($documentNumber)
            ->contains(fn (Document $document): bool => (int) $document->id === (int) $registry->source_id);
    }

    /**
     * @return Collection<int, Document>
     */
    private function reusableImportedObsoleteNumberSources(?string $documentNumber): Collection
    {
        if (! filled($documentNumber)) {
            return collect();
        }

        $obsoleteStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::OBSOLETE)
            ->value('id');

        if ($obsoleteStatusId === null) {
            return collect();
        }

        return Document::query()
            ->where('nomor_dokumen', $documentNumber)
            ->where('origin', '!=', Document::ORIGIN_WORKFLOW)
            ->where('m_status_document_id', $obsoleteStatusId)
            ->whereNull('request_type')
            ->get();
    }

    private function rebuildSameNumberImportedRevisionChain(Document $anchor, int $createdBy): void
    {
        if (! filled($anchor->nomor_dokumen)) {
            return;
        }

        $documents = $this->sameNumberImportedChainDocuments($anchor->nomor_dokumen)
            ->get()
            ->sortBy(fn (Document $document): string => sprintf(
                '%010d-%d-%010d-%010d',
                $document->numeric_revision,
                $document->isMaster() ? 1 : 0,
                $document->approved_at?->timestamp
                    ?? $document->obsolete_at?->timestamp
                    ?? $document->tanggal_terbit?->timestamp
                    ?? 0,
                $document->id,
            ))
            ->values();

        if ($documents->count() < 2) {
            return;
        }

        $previous = null;

        foreach ($documents as $document) {
            $nextRevisedFrom = $previous?->id;

            if ((int) ($document->revised_from ?? 0) !== (int) ($nextRevisedFrom ?? 0)) {
                $document->forceFill(['revised_from' => $nextRevisedFrom])->save();
            }

            $previous = $document;
        }

        $documents->each(function (Document $document, int $index) use ($documents, $createdBy): void {
            $next = $documents->get($index + 1);

            if ($next === null) {
                $document->outgoingRelations()
                    ->where('relation_type', DocumentRelation::SUPERSEDED_BY)
                    ->delete();

                return;
            }

            DocumentRelation::supersedeDocument(
                $document,
                $next,
                $createdBy,
                'Digantikan oleh versi import berikutnya dengan nomor dokumen yang sama.',
            );
        });
    }

    private function sameNumberImportedChainDocuments(?string $documentNumber): Builder
    {
        $statusIds = StatusDocument::query()
            ->whereIn('nama_status', [StatusDocument::APPROVED, StatusDocument::OBSOLETE])
            ->pluck('id');

        return Document::query()
            ->where('nomor_dokumen', $documentNumber)
            ->where('origin', '!=', Document::ORIGIN_WORKFLOW)
            ->whereIn('m_status_document_id', $statusIds)
            ->whereNull('request_type');
    }

    private function canDeleteImportedExisting(Request $request): bool
    {
        return $request->user()?->hasPermission('documents.existing.imports.delete') ?? false;
    }

    private function updateClaimedImportedDocumentNumber(Document $document, ?string $newDocumentNumber, int $userId): void
    {
        if (! filled($newDocumentNumber)) {
            return;
        }

        $approvedStatusId = StatusDocument::query()->where('nama_status', StatusDocument::APPROVED)->value('id');
        $isMaster = (int) $document->m_status_document_id === (int) $approvedStatusId;

        if ($isMaster && Document::query()
            ->where('nomor_dokumen', $newDocumentNumber)
            ->where('id', '!=', $document->id)
            ->where('m_status_document_id', $approvedStatusId)
            ->exists()) {
            throw ValidationException::withMessages([
                'nomor_dokumen' => 'Nomor dokumen sudah digunakan.',
            ]);
        }

        $existingRegistry = DocumentNumberRegistry::query()
            ->where('document_number', $newDocumentNumber)
            ->lockForUpdate()
            ->first();

        if ($existingRegistry !== null) {
            $isOwnRegistry = $existingRegistry->source_type === DocumentNumberRegistry::SOURCE_T_DOCUMENT
                && (int) $existingRegistry->source_id === (int) $document->id;

            if (! $isOwnRegistry) {
                if (! $isMaster && $existingRegistry->source_type === DocumentNumberRegistry::SOURCE_T_DOCUMENT) {
                    DocumentNumberRegistry::query()
                        ->where('source_type', DocumentNumberRegistry::SOURCE_T_DOCUMENT)
                        ->where('source_id', $document->id)
                        ->delete();

                    return;
                }

                throw ValidationException::withMessages([
                    'nomor_dokumen' => 'Nomor dokumen sudah digunakan.',
                ]);
            }
        }

        $currentRegistry = DocumentNumberRegistry::query()
            ->where('source_type', DocumentNumberRegistry::SOURCE_T_DOCUMENT)
            ->where('source_id', $document->id)
            ->first();

        if ($currentRegistry !== null) {
            $currentRegistry->update([
                'document_number' => $newDocumentNumber,
                'scope_identifier' => $this->scopeIdentifierForNumber($newDocumentNumber),
                'registered_by' => $userId,
                'registered_at' => now(),
            ]);
        } else {
            DocumentNumberRegistry::create([
                'document_number' => $newDocumentNumber,
                'scope_identifier' => $this->scopeIdentifierForNumber($newDocumentNumber),
                'source_type' => DocumentNumberRegistry::SOURCE_T_DOCUMENT,
                'source_id' => $document->id,
                'registered_by' => $userId,
                'registered_at' => now(),
            ]);
        }
    }

    private function scopeIdentifierForNumber(string $documentNumber): ?string
    {
        $segments = collect(explode('-', $documentNumber))
            ->map(fn (string $segment): string => trim($segment))
            ->filter()
            ->values();

        if ($segments->count() < 2) {
            return null;
        }

        $segments->pop();

        return $segments->implode('-');
    }

    private function normalizeDocumentNumberSuffix(?string $suffix): ?string
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

    private function procedureNumberSegmentsFromReference(?string $reference): Collection
    {
        if (! filled($reference)) {
            return collect();
        }

        $procedureNumber = DocumentRelation::targetDocumentNumber($reference);

        return collect(explode('-', (string) $procedureNumber))
            ->map(fn (string $segment): string => Str::upper(trim($segment)))
            ->filter()
            ->values()
            ->skip(1)
            ->values();
    }

    private function resolveImportedDocumentNumber(Request $request): ?string
    {
        $suffix = $this->normalizeDocumentNumberSuffix($request->input('nomor_dokumen_suffix'));

        if (filled($suffix)) {
            $request->merge(['nomor_dokumen_suffix' => $suffix]);
            $levelId = $request->input('m_document_level_id');
            $level = DocumentLevel::query()->find($levelId);
            $levelKey = $level?->kode;

            if ($levelKey === 'level-1') {
                return "SM-{$suffix}";
            }

            if ($levelKey === 'level-2') {
                $functionCode = BusinessFunction::query()->whereKey($request->input('m_proses_fungsi_id'))->value('kode');
                if (filled($functionCode)) {
                    return "PS-{$functionCode}-{$suffix}";
                }
            }

            if ($levelKey === 'level-3') {
                $reference = (string) $request->input('reference');
                $procedureSegments = $this->procedureNumberSegmentsFromReference($reference);
                if ($procedureSegments->isNotEmpty()) {
                    return collect(['IK'])
                        ->merge($procedureSegments)
                        ->push($suffix)
                        ->implode('-');
                }
            }

            return $suffix;
        }

        if ($request->filled('nomor_dokumen')) {
            $nomorDokumen = trim((string) $request->input('nomor_dokumen'));
            $segments = collect(explode('-', $nomorDokumen))->map(fn ($s) => trim($s))->filter()->values();
            if ($segments->isNotEmpty()) {
                $last = $segments->last();
                if (ctype_digit($last) && strlen($last) === 1) {
                    $segments[$segments->count() - 1] = str_pad($last, 2, '0', STR_PAD_LEFT);

                    return $segments->implode('-');
                }
            }

            return $nomorDokumen;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function stateOptions(): array
    {
        return [
            '' => 'Semua Status Existing',
            self::STATE_MASTER => 'Existing Master',
            self::STATE_OBSOLETE => 'Existing Obsolete',
        ];
    }

    private function relationDocumentOptions(?int $documentLevelId = null): array
    {
        $approvedStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::APPROVED)
            ->value('id');

        if ($approvedStatusId === null) {
            return [];
        }

        $query = Document::query()
            ->select(['id', 'nama_dokumen', 'nomor_dokumen', 'nomor_revisi', 'origin', 'm_document_level_id', 'm_proses_bisnis_id', 'm_proses_fungsi_id', 'm_status_document_id', 'request_type', 'revised_from', 'approved_at', 'tanggal_terbit'])
            ->where('m_status_document_id', $approvedStatusId)
            ->where(function ($query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', '!=', 'obsolete');
            });

        if ($documentLevelId !== null) {
            $query->where('m_document_level_id', $documentLevelId);
        }

        $documents = $query->get()
            ->groupBy(fn (Document $document): int => $document->revisionRootId())
            ->map(fn ($family): Document => $family
                ->sortByDesc(fn (Document $document): string => sprintf(
                    '%010d-%010d-%010d',
                    $document->numeric_revision,
                    $document->approved_at?->timestamp ?? $document->tanggal_terbit?->timestamp ?? 0,
                    $document->id,
                ))
                ->first())
            ->values();

        return $documents
            ->map(fn (Document $document): array => [
                'value' => 'existing-'.$document->id,
                'label' => $this->documentOptionLabel($document),
                'meta' => ($document->origin === Document::ORIGIN_WORKFLOW ? 'Dokumen Workflow' : 'Import Master').' - Revisi '.$document->formatted_revision,
                'is_master' => true,
                'document_level_id' => $document->m_document_level_id,
                'business_process_id' => $document->m_proses_bisnis_id,
                'business_function_id' => $document->m_proses_fungsi_id,
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function replacementRelationAttributes(?string $replacementReference): ?array
    {
        if (! filled($replacementReference)) {
            return null;
        }

        if (! $this->replacementReferenceIsMasterCandidate($replacementReference)) {
            return null;
        }

        $targetId = DocumentRelation::targetDocumentIdForReference($replacementReference);
        if ($targetId === null || ! Document::query()->whereKey($targetId)->exists()) {
            return null;
        }

        return [
            'target_document_id' => $targetId,
        ];
    }

    private function replacementReferenceIsMasterCandidate(string $reference): bool
    {
        return collect($this->relationDocumentOptions())
            ->contains(fn (array $document): bool => $document['value'] === $reference);
    }

    private function replacementReferenceMatchesCurrentRuleContext(array $validated): bool
    {
        $replacementReference = (string) ($validated['replacement_reference'] ?? '');

        return collect($this->relationDocumentOptions())
            ->contains(fn (array $document): bool => $document['value'] === $replacementReference
                && (string) $document['document_level_id'] === (string) ($validated['m_document_level_id'] ?? '')
                && (string) $document['business_process_id'] === (string) ($validated['m_proses_bisnis_id'] ?? '')
                && (string) $document['business_function_id'] === (string) ($validated['m_proses_fungsi_id'] ?? ''));
    }

    private function relationTargetAttributes(?string $relationReference, ?string $relationType): ?array
    {
        if (! filled($relationReference)) {
            return null;
        }

        if ($relationType === DocumentRelation::SUPERSEDED_BY) {
            return $this->replacementRelationAttributes($relationReference);
        }

        $targetId = DocumentRelation::targetDocumentIdForReference($relationReference);
        if ($targetId === null || ! Document::query()->whereKey($targetId)->exists()) {
            return null;
        }

        return [
            'target_document_id' => $targetId,
        ];
    }

    private function documentOptionLabel(Document $document): string
    {
        return trim(($document->nomor_dokumen ? $document->nomor_dokumen.' - ' : '').$document->nama_dokumen);
    }

    /**
     * @return array<string, string>
     */
    private function ruleOptions(): array
    {
        return [
            '' => 'Semua Ketentuan',
            self::CURRENT_RULE => 'Sesuai Ketentuan Saat Ini',
            self::LEGACY_RULE => 'Mengikuti Ketentuan Dokumen Lama',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function relationTypeOptions(): array
    {
        return [
            DocumentRelation::SUPERSEDED_BY => 'Digantikan Oleh',
            DocumentRelation::REFERENCES => 'Referensi',
        ];
    }
}
