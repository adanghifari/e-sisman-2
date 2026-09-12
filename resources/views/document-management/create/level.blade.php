<x-layouts.app :title="__('Tambah Dokumen')">
    @php
        $draft = $draft ?? null;
        $revisionSource = $revisionSource ?? null;
        $resubmissionSource = $resubmissionSource ?? $draft?->resubmittedFrom;
        $isEditingDraft = $draft !== null;
        $formSource = $draft ?? $resubmissionSource;
        $draftFilesByType = $formSource?->files?->groupBy('type_file') ?? collect();
        $attachmentNumberSuffix = function (?string $documentNumber): ?int {
            if (! filled($documentNumber)) {
                return null;
            }

            $suffix = \Illuminate\Support\Str::afterLast($documentNumber, '-');

            return ctype_digit($suffix) ? (int) $suffix : null;
        };
        $attachmentSortKey = fn ($file) => sprintf(
            '%010d-%010d-%010d',
            $attachmentNumberSuffix($file->document_number) ?? PHP_INT_MAX,
            $file->attachment_order ?? PHP_INT_MAX,
            $file->id,
        );
        $existingFilePayload = fn (string $type) => $draftFilesByType
            ->get($type, collect())
            ->sortBy($attachmentSortKey)
            ->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->original_file_name,
                'title' => $file->attachment_title,
                'order' => $file->attachment_order,
                'size' => $file->file_size,
                'document_number' => $file->document_number,
            ])
            ->values();
        $revisionSourceAttachments = $revisionSource
            ? $revisionSource->availableRevisionSourceAttachments()
            : collect();
        $revisionSourceCurrentAttachmentIds = $revisionSource
            ? $revisionSource->files()
                ->where('type_file', 'attachment')
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all()
            : [];
        $carriedForwardSourceFileIds = $draft
            ? $draft->files
                ->where('type_file', 'attachment')
                ->pluck('source_file_id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->all()
            : [];
        $levelKey ??= request()->route('level') ?? $draft?->documentLevel?->kode;
        $level = config("document-levels.{$levelKey}");
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
            'level-4' => 'IV',
        ];
        $documentPrefixes = [
            'level-1' => 'SM',
            'level-2' => 'PS',
            'level-3' => 'IK',
            'level-4' => 'FM',
        ];
        $revisionPrefixes = [
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
        ];
        $revisionSourceMaster = $revisionSource?->documentLevel?->kode === 'level-4' && $revisionSource?->revisedFrom
            ? $revisionSource->revisedFrom
            : $revisionSource;
        $revisionSourceLevelKey = $revisionSourceMaster?->documentLevel?->kode;
        $levelFourPrefix = match ($revisionSourceLevelKey) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };
        $levelFourFormTitle = match ($revisionSourceLevelKey) {
            'level-1' => 'Form Manual',
            'level-2' => 'Form Prosedur',
            'level-3' => 'Form Instruksi Kerja',
            default => 'Form/Lembar Revisi',
        };
        $revisionSourceLevelDisplayValue = $revisionSourceMaster?->documentLevel
            ? $revisionSourceMaster->documentLevel->nama_level.': '.\Illuminate\Support\Str::after($revisionSourceMaster->documentLevel->nama_dokumen ?? '', ': ')
            : '-';
        $revisionSourceImportTitle = $revisionSourceMaster?->documentLevel
            ? 'Import Dokumen '.$revisionSourceLevelDisplayValue
            : 'Import Dokumen';

        $ownerLabel = $levelKey === 'level-1' ? 'Penyusun Dokumen' : 'Penyusun Pemilik Proses';
        $documentTitle = \Illuminate\Support\Str::after($level['name'], ': ');
        $documentLevelRecord = \Illuminate\Support\Facades\Schema::hasTable('m_document_levels')
            ? \App\Models\DocumentLevel::query()->where('kode', $levelKey)->first()
            : null;
        $levelDisplayValue = $documentLevelRecord
            ? $documentLevelRecord->nama_level.' : '.\Illuminate\Support\Str::after($documentLevelRecord->nama_dokumen, ': ')
            : $level['badge'].' : '.$documentTitle;
        $businessProcesses = \Illuminate\Support\Facades\Schema::hasTable('m_proses_bisnis')
            ? \App\Models\BusinessProcess::query()->active()->orderBy('nama_proses_bisnis')->get()
            : collect();
        $businessFunctions = \Illuminate\Support\Facades\Schema::hasTable('m_proses_fungsi')
            ? \App\Models\BusinessFunction::query()->active()->orderBy('nama_proses_fungsi')->get()
            : collect();
        $departments = \Illuminate\Support\Facades\Schema::hasTable('departments')
            ? \App\Models\Department::query()->active()->orderBy('nama_department')->get()
            : collect();
        $procedureLevelId = \Illuminate\Support\Facades\Schema::hasTable('m_document_levels')
            ? \App\Models\DocumentLevel::query()->where('kode', 'level-2')->value('id')
            : null;
        $approvedStatusId = \Illuminate\Support\Facades\Schema::hasTable('m_status_document')
            ? \App\Models\StatusDocument::query()->where('nama_status', \App\Models\StatusDocument::APPROVED)->value('id')
            : null;
        $procedureReferences ??= ($levelKey === 'level-3' && $procedureLevelId && $approvedStatusId)
            ? \App\Models\Document::query()
                ->select([
                    'id',
                    'nomor_dokumen',
                    'nama_dokumen',
                    'm_proses_bisnis_id',
                    'm_proses_fungsi_id',
                ])
                ->where('m_document_level_id', $procedureLevelId)
                ->where('m_status_document_id', $approvedStatusId)
                ->orderBy('nomor_dokumen')
                ->get()
            : collect();
        $draftStatusId = \Illuminate\Support\Facades\Schema::hasTable('m_status_document')
            ? \App\Models\StatusDocument::query()->where('nama_status', \App\Models\StatusDocument::DRAFT)->value('id')
            : null;
        $documentNumberSequence = function (?string $documentNumber): ?int {
            if (! filled($documentNumber)) {
                return null;
            }

            $suffix = \Illuminate\Support\Str::afterLast($documentNumber, '-');

            return ctype_digit($suffix) ? (int) $suffix : null;
        };
        $documentNumberScope = function (?string $documentNumber): ?string {
            if (! filled($documentNumber)) {
                return null;
            }

            $segments = collect(explode('-', $documentNumber))
                ->map(fn (string $segment): string => trim($segment))
                ->filter()
                ->values();

            if ($segments->count() < 2 || ! ctype_digit((string) $segments->last())) {
                return null;
            }

            $segments->pop();

            return $segments->implode('-');
        };
        $documentNumberSuggestions = [];
        if ($documentLevelRecord && in_array($levelKey, ['level-2', 'level-3'], true)) {
            $documentNumbers = \App\Models\Document::query()
                ->where('m_document_level_id', $documentLevelRecord->id)
                ->whereNull('revised_from')
                ->where('nomor_revisi', 0)
                ->whereNotNull('nomor_dokumen')
                ->when($draftStatusId, fn ($query) => $query->where('m_status_document_id', '!=', $draftStatusId))
                ->pluck('nomor_dokumen');

            if (\Illuminate\Support\Facades\Schema::hasTable('document_number_registry')) {
                $documentNumbers = $documentNumbers->merge(
                    \App\Models\DocumentNumberRegistry::query()
                        ->where('scope_identifier', 'like', $documentPrefixes[$levelKey].'-%')
                        ->pluck('document_number'),
                );
            }

            $documentNumberSuggestions = $documentNumbers
                ->map(fn ($documentNumber) => [
                    'scope' => $documentNumberScope($documentNumber),
                    'sequence' => $documentNumberSequence($documentNumber),
                ])
                ->filter(fn (array $number): bool => filled($number['scope']) && $number['sequence'] !== null)
                ->groupBy('scope')
                ->map(function ($numbers) {
                    $nextSequence = ((int) $numbers->pluck('sequence')->max()) + 1;

                    return str_pad((string) $nextSequence, 2, '0', STR_PAD_LEFT);
                })
                ->mapWithKeys(fn (string $suggestion, string $scope): array => ["scope:{$scope}" => $suggestion])
                ->all();

            $businessFunctions->each(function ($businessFunction) use (&$documentNumberSuggestions, $levelKey): void {
                if (! filled($businessFunction->kode)) {
                    return;
                }

                $scope = $levelKey === 'level-2'
                    ? 'PS-'.$businessFunction->kode
                    : null;

                if ($scope !== null && isset($documentNumberSuggestions["scope:{$scope}"])) {
                    $documentNumberSuggestions[$businessFunction->id] = $documentNumberSuggestions["scope:{$scope}"];
                }
            });
        }
        $nextLevelOneDocumentNumberSuffix = null;
        if ($levelKey === 'level-1') {
            $manualDocumentNumbers = collect();

            if ($documentLevelRecord) {
                $manualDocumentNumbers = $manualDocumentNumbers->merge(
                    \App\Models\Document::query()
                        ->where('m_document_level_id', $documentLevelRecord->id)
                        ->whereNotNull('nomor_dokumen')
                        ->when($draft?->id, fn ($query) => $query->whereKeyNot($draft->id))
                        ->pluck('nomor_dokumen'),
                );
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('document_number_registry')) {
                $manualDocumentNumbers = $manualDocumentNumbers->merge(
                    \App\Models\DocumentNumberRegistry::query()
                        ->where('scope_identifier', 'SM')
                        ->pluck('document_number'),
                );
            }

            $manualNextSequence = ((int) $manualDocumentNumbers
                ->map(fn ($documentNumber) => $documentNumberSequence($documentNumber))
                ->filter()
                ->max()) + 1;
            $manualReservedStart = \Illuminate\Support\Facades\Schema::hasTable('document_numbering_setups')
                ? (int) \App\Models\DocumentNumberingSetup::query()
                    ->where('scope_identifier', 'SM')
                    ->value('v2_start_number')
                : 0;
            $manualNextSequence = max($manualNextSequence, $manualReservedStart);
            $nextLevelOneDocumentNumberSuffix = str_pad((string) $manualNextSequence, 3, '0', STR_PAD_LEFT);
        }
        $departmentOptions = $departments
            ->map(fn ($department) => [
                'value' => $department->id,
                'label' => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department,
            ])
            ->values();
        $formatBusinessProcess = fn ($businessProcess) => $businessProcess
            ? (($businessProcess->kode ? $businessProcess->kode.' - ' : '').$businessProcess->nama_proses_bisnis)
            : '-';
        $formatBusinessFunction = fn ($businessFunction) => $businessFunction
            ? (($businessFunction->kode ? $businessFunction->kode.' - ' : '').$businessFunction->nama_proses_fungsi)
            : '-';
        $revisionDocumentSuffix = null;
        $revisionDocumentNumberSegments = [];
        if ($revisionSourceMaster?->nomor_dokumen) {
            $sourceNumberSegments = collect(explode('-', $revisionSourceMaster->nomor_dokumen))
                ->filter()
                ->values();

            if ($levelKey === 'level-4') {
                $sourceNumberBodySegments = $sourceNumberSegments->skip(1)->values();
                $revisionDocumentNumberSegments = $sourceNumberBodySegments->slice(0, -1)->values()->all();
                $revisionDocumentSuffix = $sourceNumberBodySegments->last();
            } else {
                $revisionDocumentSuffix = \Illuminate\Support\Str::afterLast($revisionSourceMaster->nomor_dokumen, '-');
            }
        }
        $documentNumberPrefix = $revisionSource
            ? ($levelKey === 'level-4' ? $levelFourPrefix : ($revisionPrefixes[$levelKey] ?? 'FM'.$documentPrefixes[$levelKey]))
            : $documentPrefixes[$levelKey];
        $latestRevisionNumber = $revisionSource
            ? $revisionSource->latestApprovedRevisionNumber()
            : null;
        $documentNumberSuffixDefault = $formSource?->nomor_dokumen
            ? \Illuminate\Support\Str::afterLast($formSource->nomor_dokumen, '-')
            : $revisionDocumentSuffix;
        if (! $revisionSource && ! $formSource && $levelKey === 'level-1') {
            $documentNumberSuffixDefault = $nextLevelOneDocumentNumberSuffix;
        }
        $revisionFormDisplayNumber = $revisionSource
            ? ($formSource?->nomor_lembar_revisi ?: app(\App\Support\DocumentFiles\DocumentFileNumbering::class)->revisionFormNumber($revisionSource))
            : null;
        $revisionFormDisplaySegments = $revisionFormDisplayNumber
            ? collect(explode('-', $revisionFormDisplayNumber))->filter()->values()
            : collect();
        $selectedBusinessProcessId = old('m_proses_bisnis_id', $formSource?->m_proses_bisnis_id ?? $revisionSource?->m_proses_bisnis_id);
        $selectedBusinessFunctionId = old('m_proses_fungsi_id', $formSource?->m_proses_fungsi_id ?? $revisionSource?->m_proses_fungsi_id);
        $selectedReferenceId = old('reference', $formSource?->procedureReferenceValue() ?? $revisionSource?->procedureReferenceValue());
        $selectedDepartmentIds = old('department_ids', $draft
            ? $draft->departments->pluck('id')->all()
            : ($resubmissionSource
                ? $resubmissionSource->departments->pluck('id')->all()
                : collect($revisionSource?->departments ?? [])->pluck('id')->all()));
        $revisionDocumentNameValue = old('nama_dokumen', $formSource?->nama_dokumen ?? $revisionSource?->nama_dokumen);
        $revisionNameEditorStartsOpen = $revisionSource && $errors->has('nama_dokumen');
        $nextRevisionValue = $draft
            ? $draft->formatted_revision
            : ($revisionSource
            ? \App\Models\Document::formatRevisionNumber($resubmissionSource?->nomor_revisi ?? (($latestRevisionNumber ?? $revisionSource->nomor_revisi) + 1))
            : '00.00');
        $selectedBusinessFunction = $businessFunctions->firstWhere('id', (int) $selectedBusinessFunctionId);
        $documentNumberFunctionCode = $selectedBusinessFunction?->kode ?: 'XXX';
        $selectedProcedureReference = $procedureReferences->firstWhere('reference_value', $selectedReferenceId);
        $procedureReferenceSegments = fn ($procedure) => collect(explode('-', (string) ($procedure?->procedure_reference_number ?: $procedure?->nomor_dokumen)))
            ->filter()
            ->values()
            ->skip(1)
            ->values();
        $selectedProcedureNumberSegments = $procedureReferenceSegments($selectedProcedureReference);
        $procedureReferenceNumberSegments = $procedureReferences
            ->mapWithKeys(fn ($procedure) => [
                $procedure->reference_value => $procedureReferenceSegments($procedure)->all(),
            ])
            ->all();
        $documentNumberSegments = match ($levelKey) {
            'level-2' => [['value' => $documentNumberFunctionCode, 'target' => 'business-function']],
            'level-3' => [
                ['value' => $selectedProcedureNumberSegments->get(0, 'XXX'), 'target' => 'procedure-reference-0'],
                ['value' => $selectedProcedureNumberSegments->get(1, 'YY'), 'target' => 'procedure-reference-1'],
            ],
            'level-4' => $revisionFormDisplaySegments->slice(1, -1)->values()->all() ?: $revisionDocumentNumberSegments,
            default => [],
        };
        $documentNumberPrefix = $levelKey === 'level-4' && $revisionFormDisplaySegments->isNotEmpty()
            ? $revisionFormDisplaySegments->first()
            : $documentNumberPrefix;
        $documentNumberSuffixDefault = $levelKey === 'level-4' && $revisionFormDisplaySegments->isNotEmpty()
            ? $revisionFormDisplaySegments->last()
            : $documentNumberSuffixDefault;
        $assignableUsers = \App\Models\User::query()
            ->with('department')
            ->whereKeyNot(auth()->id())
            ->orderBy('name')
            ->get();
    @endphp

    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700">Home</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.create') }}" class="transition hover:text-sky-700">Tambah Dokumen</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $level['badge'] }}</span>
        </nav>

        <div>
            <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                {{ $levelKey === 'level-4' && $revisionSource ? 'Dokumen Level IV: '.$levelFourFormTitle : (($revisionSource ? 'Ajukan Revisi' : ($levelKey === 'level-1' ? 'Import' : 'Tambah')).' Dokumen Level '.$levelNumbers[$levelKey].' : '.$documentTitle) }}
            </h1>

            @if ($levelKey === 'level-4' && $revisionSource)
                <p class="mt-2 text-sm font-medium text-slate-500">{{ $revisionSourceImportTitle }}</p>
            @endif
        </div>

        @if ($levelKey === 'level-1')
            <form method="POST" action="{{ route('documents.store', $levelKey) }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]" data-document-autosave-form data-autosave-url="{{ route('documents.autosave', $levelKey) }}" data-loading-overlay-form data-loading-overlay-timeout="60000" data-loading-overlay-title="Loading..." data-loading-overlay-description="Dokumen sedang diproses. Mohon tunggu sebentar.">
                @csrf
                <input type="hidden" name="draft_id" value="{{ $draft?->id }}" data-autosave-draft-id>
                @if ($revisionSource)
                    <input type="hidden" name="revised_from" value="{{ $revisionSource->id }}">
                    @if (request()->filled('imported_source') || $revisionSource->origin !== \App\Models\Document::ORIGIN_WORKFLOW)
                        <input type="hidden" name="imported_source" value="{{ $revisionSource->id }}">
                    @endif
                @endif
                @if ($resubmissionSource)
                    <input type="hidden" name="resubmitted_from" value="{{ $resubmissionSource->id }}">
                @endif

                <div class="space-y-6">
                    <section class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Informasi Dokumen</h2>
                        </div>

                        <div class="px-6 py-6">
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                                <input
                                    type="text"
                                    name="nama_dokumen"
                                    value="{{ old('nama_dokumen', $formSource?->nama_dokumen ?? $revisionSource?->nama_dokumen) }}"
                                    placeholder="Masukan nama dokumen"
                                    required
                                    @class([
                                        'h-14 w-full rounded-lg bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:ring-2',
                                        'border border-red-300 focus:border-red-400 focus:ring-red-100' => $errors->has('nama_dokumen'),
                                        'border border-slate-300 focus:border-sky-400 focus:ring-sky-100' => ! $errors->has('nama_dokumen'),
                                    ])
                                >
                                @error('nama_dokumen')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>
                    </section>

                    <x-documents.official-preparer
                        :label="$ownerLabel"
                        :users="$assignableUsers"
                        :selected-user="$draft?->officialPreparer"
                    />

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Upload Dokumen</h2>
                        </div>

                        <div class="space-y-5 px-6 py-6">
                            <x-ui.file-upload
                                label="Import Dokumen"
                                name="imported_document"
                                accept=".pdf,application/pdf"
                                hint="Format PDF."
                                :max-files="1"
                                :max-file-size-kb="10240"
                                :required="$draftFilesByType->get('imported_document', collect())->isEmpty()"
                                :existing-files="$existingFilePayload('imported_document')"
                            />
                            @error('imported_document')
                                <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror

                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Catatan</span>
                                <textarea
                                    name="catatan_revisi"
                                    rows="5"
                                    placeholder="Tambahkan catatan dokumen"
                                    class="w-full resize-none rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >{{ old('catatan_revisi', $formSource?->catatan_revisi) }}</textarea>
                                @error('catatan_revisi')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm xl:sticky xl:top-8">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                    </div>

                    <div class="space-y-5 px-6 py-6">
                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Dokumen</span>
                            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4">
                                <input type="text" value="{{ $documentNumberPrefix }}" readonly class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">
                                <span class="text-lg font-semibold text-slate-500">-</span>
                                <input
                                    type="text"
                                    name="nomor_dokumen_suffix"
                                    value="{{ old('nomor_dokumen_suffix', $documentNumberSuffixDefault) }}"
                                    required
                                    @readonly($resubmissionSource)
                                    class="h-14 w-full rounded-lg border {{ $resubmissionSource ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-slate-300 bg-white text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-100' }} px-3 text-center text-base font-semibold outline-none transition"
                                >
                            </div>
                            @error('nomor_dokumen_suffix')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                            <input
                                type="text"
                                name="nomor_revisi"
                                value="{{ old('nomor_revisi', $formSource?->formatted_revision ?? $nextRevisionValue) }}"
                                @readonly($revisionSource)
                                class="h-14 w-full rounded-lg border {{ $revisionSource ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-slate-300 bg-white text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-100' }} px-4 text-base font-semibold outline-none transition"
                            >
                            @error('nomor_revisi')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <x-ui.date-input
                            label="Tanggal Terbit"
                            name="tanggal_terbit"
                            :value="old('tanggal_terbit', $formSource?->tanggal_terbit?->format('Y-m-d'))"
                        />
                    </div>

                    <div class="border-t border-dashed border-slate-200 px-6 py-5">
                        <p class="mb-3 text-center text-xs font-semibold text-slate-500" data-autosave-status>
                            Draft akan tersimpan otomatis saat Anda mengisi form.
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <button type="submit" name="submit_action" value="draft" formnovalidate data-loading-overlay-skip="true" class="inline-flex h-12 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:bg-slate-50">
                                Simpan Draft
                            </button>
                            <button type="submit" name="submit_action" value="submit" class="inline-flex h-12 items-center justify-center rounded-lg bg-blue-500 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-blue-600">
                                Submit Dokumen
                            </button>
                        </div>
                    </div>
                </aside>
            </form>
        @else
            <form method="POST" action="{{ route('documents.store', $levelKey) }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_520px]" data-document-create-form data-document-autosave-form data-autosave-url="{{ route('documents.autosave', $levelKey) }}" data-max-total-file-size-kb="25600" data-loading-overlay-form data-loading-overlay-timeout="60000" data-loading-overlay-title="Loading..." data-loading-overlay-description="Dokumen sedang diproses. Mohon tunggu sebentar.">
                @csrf
                <input type="hidden" name="draft_id" value="{{ $draft?->id }}" data-autosave-draft-id>
                @if ($revisionSource)
                    <input type="hidden" name="revised_from" value="{{ $revisionSource->id }}">
                    @if (request()->filled('imported_source') || $revisionSource->origin !== \App\Models\Document::ORIGIN_WORKFLOW)
                        <input type="hidden" name="imported_source" value="{{ $revisionSource->id }}">
                    @endif
                @endif
                @if ($resubmissionSource)
                    <input type="hidden" name="resubmitted_from" value="{{ $resubmissionSource->id }}">
                @endif

                <div class="space-y-6">
                    @if ($errors->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                            <p>Submit dokumen belum berhasil. Cek isian berikut:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <x-documents.form-section title="Informasi Dokumen">
                        @if ($revisionSource)
                            <input type="hidden" name="m_document_level_id" value="{{ $documentLevelRecord?->id }}">
                            <input type="hidden" name="m_proses_bisnis_id" value="{{ $revisionSource->m_proses_bisnis_id }}">
                            <input type="hidden" name="m_proses_fungsi_id" value="{{ $revisionSource->m_proses_fungsi_id }}">
                            @foreach ($selectedDepartmentIds as $departmentId)
                                <input type="hidden" name="department_ids[]" value="{{ $departmentId }}">
                            @endforeach
                            @if ($levelKey === 'level-3')
                                <input type="hidden" name="reference" value="{{ $revisionSource->procedureReferenceValue() }}">
                            @endif

                            <dl class="divide-y divide-slate-100 px-6 py-4">
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]" data-revision-name-editor data-initial-name="{{ $revisionDocumentNameValue }}">
                                    <dt class="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                        <span>Nama Dokumen</span>
                                        <span class="inline-flex items-center gap-1">
                                            <button
                                                type="button"
                                                @class([
                                                    'inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-orange-200 hover:bg-orange-50 hover:text-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-100',
                                                    'border-orange-200 bg-orange-50 text-orange-600' => $revisionNameEditorStartsOpen,
                                                ])
                                                data-revision-name-edit-toggle
                                                data-editing="{{ $revisionNameEditorStartsOpen ? 'true' : 'false' }}"
                                                aria-label="{{ $revisionNameEditorStartsOpen ? 'Batal ubah nama dokumen' : 'Ubah nama dokumen' }}"
                                            >
                                                <span @class(['hidden' => $revisionNameEditorStartsOpen]) data-revision-name-edit-icon>
                                                    <x-flux.icon name="pencil-square" class="size-4" />
                                                </span>
                                                <span @class(['hidden' => ! $revisionNameEditorStartsOpen]) data-revision-name-cancel-icon>
                                                    <x-flux.icon name="x-mark" class="size-4" />
                                                </span>
                                            </button>
                                            <button
                                                type="button"
                                                @class([
                                                    'inline-flex size-9 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 transition hover:border-emerald-300 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-100',
                                                    'hidden' => ! $revisionNameEditorStartsOpen,
                                                ])
                                                data-revision-name-save
                                                aria-label="Simpan nama dokumen"
                                                style="{{ $revisionNameEditorStartsOpen ? '' : 'display: none;' }}"
                                            >
                                                <x-flux.icon name="check" class="size-4" />
                                            </button>
                                        </span>
                                    </dt>
                                    <dd class="min-w-0">
                                        <p @class(['text-sm font-bold leading-6 text-slate-900', 'hidden' => $revisionNameEditorStartsOpen]) data-revision-name-display>
                                            {{ $revisionDocumentNameValue }}
                                        </p>
                                        <div @class(['space-y-2', 'hidden' => ! $revisionNameEditorStartsOpen]) data-revision-name-input-wrap>
                                            <input
                                                type="text"
                                                name="nama_dokumen"
                                                value="{{ $revisionDocumentNameValue }}"
                                                required
                                                class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-orange-300 focus:ring-2 focus:ring-orange-100"
                                                data-revision-name-input
                                            >
                                            <div>
                                                @error('nama_dokumen')
                                                    <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </dd>
                                </div>
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Level Dokumen</dt>
                                    <dd class="text-sm font-bold text-slate-900">{{ $levelKey === 'level-4' ? $revisionSourceLevelDisplayValue : ($levelDisplayValue ?: '-') }}</dd>
                                </div>
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">{{ $levelKey === 'level-4' ? 'Nomor Dokumen Induk' : 'Nomor Dokumen' }}</dt>
                                    <dd class="text-sm font-bold text-slate-900">{{ $revisionSource->nomor_dokumen ?: '-' }}</dd>
                                </div>
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Proses Bisnis</dt>
                                    <dd class="text-sm font-bold text-slate-900">{{ $formatBusinessProcess($revisionSource->businessProcess) }}</dd>
                                </div>
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Proses / Fungsi</dt>
                                    <dd class="text-sm font-bold text-slate-900">{{ $formatBusinessFunction($revisionSource->businessFunction) }}</dd>
                                </div>
                                <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                    <dt class="text-sm font-semibold text-slate-500">Department Terkait</dt>
                                    <dd class="text-sm font-bold text-slate-900">
                                        {{ $revisionSource->departments->map(fn ($department) => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department)->implode(', ') ?: '-' }}
                                    </dd>
                                </div>
                                @if ($revisionReference = $revisionSource->procedureReferenceRelation()?->target())
                                    <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                        <dt class="text-sm font-semibold text-slate-500">Dokumen Acuan</dt>
                                        <dd class="text-sm font-bold text-slate-900">
                                            {{ $revisionReference->nomor_dokumen ?: '-' }} - {{ $revisionReference->nama_dokumen }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        @else
                            <div class="grid gap-5 px-6 py-6 md:grid-cols-2">
                                <label class="block md:col-span-2">
                                    <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                                    <input
                                        type="text"
                                        name="nama_dokumen"
                                        value="{{ old('nama_dokumen', $formSource?->nama_dokumen) }}"
                                        placeholder="Masukan nama dokumen"
                                        required
                                        @class([
                                            'h-14 w-full rounded-lg bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:ring-2',
                                            'border border-red-300 focus:border-red-400 focus:ring-red-100' => $errors->has('nama_dokumen'),
                                            'border border-slate-300 focus:border-sky-400 focus:ring-sky-100' => ! $errors->has('nama_dokumen'),
                                        ])
                                    >
                                    @error('nama_dokumen')
                                        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                </label>

                                <input type="hidden" name="m_document_level_id" value="{{ $documentLevelRecord?->id }}">

                                <label @class(['block', 'md:col-span-2' => $levelKey !== 'level-3'])>
                                    <span class="mb-2 block text-base font-medium text-slate-500">Proses Bisnis</span>
                                    <select name="m_proses_bisnis_id" required class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                        <option value="">-Pilih-</option>
                                        @foreach ($businessProcesses as $businessProcess)
                                            <option
                                                value="{{ $businessProcess->id }}"
                                                @selected((string) $selectedBusinessProcessId === (string) $businessProcess->id)
                                            >
                                                {{ $formatBusinessProcess($businessProcess) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('m_proses_bisnis_id')
                                        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-base font-medium text-slate-500">Proses / Fungsi</span>
                                    <select name="m_proses_fungsi_id" required class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                        <option value="">-Pilih-</option>
                                        @foreach ($businessFunctions as $businessFunction)
                                            <option
                                                value="{{ $businessFunction->id }}"
                                                data-function-code="{{ $businessFunction->kode }}"
                                                @selected((string) $selectedBusinessFunctionId === (string) $businessFunction->id)
                                            >
                                                {{ $formatBusinessFunction($businessFunction) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('m_proses_fungsi_id')
                                        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                </label>

                                <x-ui.multi-select
                                    label="Department Terkait"
                                    name="department_ids"
                                    :options="$departmentOptions"
                                    :selected="$selectedDepartmentIds"
                                    selected-placeholder="Tambah Department"
                                    required
                                    class="md:col-span-1"
                                />

                                @if ($levelKey === 'level-3')
                                    <label class="block">
                                        <span class="mb-2 block text-base font-medium text-slate-500">Pilih Dokumen Level II: Prosedur</span>
                                        <select name="reference" class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                            <option value="">-Pilih-</option>
                                            @foreach ($procedureReferences as $procedureReference)
                                                <option
                                                    value="{{ $procedureReference->reference_value }}"
                                                    data-business-process-id="{{ $procedureReference->m_proses_bisnis_id }}"
                                                    data-business-function-id="{{ $procedureReference->m_proses_fungsi_id }}"
                                                    @selected((string) $selectedReferenceId === (string) $procedureReference->reference_value)
                                                >
                                                    {{ $procedureReference->procedure_reference_number ?: $procedureReference->nomor_dokumen ?: '-' }} - {{ $procedureReference->nama_dokumen }} ({{ $procedureReference->reference_source_label }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('reference')
                                            <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                        @enderror
                                    </label>
                                @endif
                            </div>
                        @endif
                    </x-documents.form-section>

                    <x-documents.official-preparer
                        :label="$ownerLabel"
                        :users="$assignableUsers"
                        :selected-user="$formSource?->officialPreparer"
                    />

                    <x-documents.form-section :title="$levelKey === 'level-4' ? 'Dokumen Revisi' : 'Isi Dokumen'" icon="cloud-arrow-up">
                        <div class="space-y-6 px-6 py-6">
                            <div class="hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" data-total-file-size-error></div>

                            @error('files')
                                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $message }}</div>
                            @enderror

                            @if ($levelKey === 'level-4')
                                <x-documents.upload-toggle-card
                                    title="1. Lembar Revisi"
                                    tone="sky"
                                >
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <div>
                                            <x-ui.file-upload
                                                label="Upload Lembar Revisi PDF"
                                                name="revision_form"
                                                accept=".pdf,application/pdf"
                                                hint="Format PDF."
                                                file-type-badge="PDF"
                                                :file-type-icon="asset('image/icon_PDF.webp')"
                                                :max-files="1"
                                                :max-file-size-kb="10240"
                                                :required="old('submit_action') === 'submit' && $draftFilesByType->get('revision_form', collect())->isEmpty()"
                                                :existing-files="$existingFilePayload('revision_form')"
                                            />

                                            @error('revision_form')
                                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div>
                                            <x-ui.file-upload
                                                label="Upload Lembar Revisi Word"
                                                name="revision_form_word"
                                                accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                                hint="Format Word."
                                                file-type-badge="WORD"
                                                :file-type-icon="asset('image/icon_word.webp')"
                                                :max-files="1"
                                                :max-file-size-kb="10240"
                                                :required="old('submit_action') === 'submit' && $draftFilesByType->get('revision_form_word', collect())->isEmpty()"
                                                :existing-files="$existingFilePayload('revision_form_word')"
                                            />

                                            @error('revision_form_word')
                                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </x-documents.upload-toggle-card>

                                <x-documents.upload-toggle-card
                                    title="2. Dokumen Revisi"
                                    tone="sky"
                                >
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <div>
                                            <x-ui.file-upload
                                                label="Upload Dokumen Revisi PDF"
                                                name="revision_content"
                                                accept=".pdf,application/pdf"
                                                hint="Format PDF."
                                                file-type-badge="PDF"
                                                :file-type-icon="asset('image/icon_PDF.webp')"
                                                :max-files="1"
                                                :max-file-size-kb="10240"
                                                :required="old('submit_action') === 'submit' && $draftFilesByType->get('revision_content', collect())->isEmpty()"
                                                :existing-files="$existingFilePayload('revision_content')"
                                            />

                                            @error('revision_content')
                                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div>
                                            <x-ui.file-upload
                                                label="Upload Dokumen Revisi Word"
                                                name="revision_content_word"
                                                accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                                hint="Format Word."
                                                file-type-badge="WORD"
                                                :file-type-icon="asset('image/icon_word.webp')"
                                                :max-files="1"
                                                :max-file-size-kb="10240"
                                                :required="old('submit_action') === 'submit' && $draftFilesByType->get('revision_content_word', collect())->isEmpty()"
                                                :existing-files="$existingFilePayload('revision_content_word')"
                                            />

                                            @error('revision_content_word')
                                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </x-documents.upload-toggle-card>
                            @else
                                <x-documents.upload-toggle-card
                                    title="Template Dokumen yang Sudah Diisi"
                                    tone="sky"
                                >
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <div>
                                            <x-ui.file-upload
                                                label="Upload Template Terisi PDF"
                                                name="filled_template"
                                                accept=".pdf,application/pdf"
                                                hint="Format PDF."
                                                file-type-badge="PDF"
                                                :file-type-icon="asset('image/icon_PDF.webp')"
                                                :max-files="1"
                                                :max-file-size-kb="10240"
                                                :required="$draftFilesByType->get('filled_template', collect())->isEmpty()"
                                                :existing-files="$existingFilePayload('filled_template')"
                                            />

                                            @error('filled_template')
                                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div>
                                            <x-ui.file-upload
                                                label="Upload Template Terisi Word"
                                                name="filled_template_word"
                                                accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                                hint="Format Word."
                                                file-type-badge="WORD"
                                                :file-type-icon="asset('image/icon_word.webp')"
                                                :max-files="1"
                                                :max-file-size-kb="10240"
                                                :required="$draftFilesByType->get('filled_template_word', collect())->isEmpty()"
                                                :existing-files="$existingFilePayload('filled_template_word')"
                                            />

                                            @error('filled_template_word')
                                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </x-documents.upload-toggle-card>
                            @endif

                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-4">
                                <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                                    <span class="min-w-0">
                                        <span class="block text-base font-bold text-slate-900">Daftar Lampiran</span>
                                        <span class="mt-2 inline-flex rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">
                                            Lampiran
                                        </span>
                                    </span>
                                </div>

                                <x-documents.attachment-list :existing-files="$existingFilePayload('attachment')" :show-create-controls="false" />

                                @if ($revisionSource && $revisionSourceAttachments->isNotEmpty())
                                    <div class="mt-5 border-t border-slate-200 pt-5">
                                        <div class="mb-3">
                                            <p class="text-sm font-bold text-slate-900">Lampiran Master Sebelumnya</p>
                                        </div>

                                        <div class="space-y-2">
                                            @foreach ($revisionSourceAttachments as $sourceAttachment)
                                                @php
                                                    $isSourceAttachmentIncluded = $draft
                                                        ? in_array((string) $sourceAttachment->id, $carriedForwardSourceFileIds, true)
                                                        : in_array((string) $sourceAttachment->id, $revisionSourceCurrentAttachmentIds, true);
                                                @endphp
                                                <div
                                                    class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 md:grid-cols-[minmax(0,1fr)_auto]"
                                                    data-master-attachment-row
                                                    data-included="{{ $isSourceAttachmentIncluded ? 'true' : 'false' }}"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="included_attachment_ids[]"
                                                        value="{{ $sourceAttachment->id }}"
                                                        data-master-attachment-include
                                                        @disabled(! $isSourceAttachmentIncluded)
                                                    >

                                                    <div class="grid min-w-0 gap-3 md:grid-cols-[auto_minmax(0,1fr)]">
                                                        <label class="flex w-36 shrink-0 cursor-pointer flex-col items-center gap-2">
                                                            <input
                                                                type="checkbox"
                                                                class="size-5 rounded border-slate-300 text-sky-600"
                                                                data-master-attachment-checkbox
                                                                @checked($isSourceAttachmentIncluded)
                                                            >
                                                            <span
                                                                @class([
                                                                    'inline-flex min-w-32 justify-center whitespace-nowrap rounded-full px-3 py-1 text-center text-[11px] font-bold',
                                                                    'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' => $isSourceAttachmentIncluded,
                                                                    'bg-red-50 text-red-700 ring-1 ring-red-100' => ! $isSourceAttachmentIncluded,
                                                                ])
                                                                data-master-attachment-badge
                                                            >
                                                                {{ $isSourceAttachmentIncluded ? 'Dicantumkan' : 'Tidak Dicantumkan' }}
                                                            </span>
                                                        </label>

                                                        <span class="min-w-0">
                                                            <span class="block truncate text-sm font-bold text-slate-900">{{ $sourceAttachment->attachment_title ?: $sourceAttachment->original_file_name }}</span>
                                                            <span class="mt-1 block truncate text-xs font-medium text-slate-500">{{ $sourceAttachment->document_number ?: 'Nomor lampiran akan disinkronkan saat submit' }}</span>
                                                        </span>

                                                        <div
                                                            class="mt-3 hidden max-w-2xl grid-cols-[minmax(0,1fr)_auto] items-stretch gap-3"
                                                            data-revised-attachment-file
                                                            data-source-file-name="{{ $sourceAttachment->original_file_name }}"
                                                        >
                                                            <label class="grid min-h-16 cursor-pointer grid-cols-[auto_minmax(0,1fr)] items-center gap-3 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-3 transition hover:border-sky-300 hover:bg-sky-50">
                                                                <span class="grid size-12 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500" data-revised-attachment-icon>
                                                                    <x-flux.icon name="arrow-up-tray" class="size-6" />
                                                                </span>
                                                                <span class="min-w-0">
                                                                    <span class="block truncate text-sm font-bold text-slate-800" data-revised-attachment-name>Pilih file PDF</span>
                                                                    <span class="block text-xs font-medium text-slate-500" data-revised-attachment-meta>Maksimal 10 MB</span>
                                                                </span>
                                                                <input
                                                                    type="file"
                                                                    name="revised_attachments[{{ $sourceAttachment->id }}]"
                                                                    accept=".pdf,application/pdf"
                                                                    class="sr-only"
                                                                    data-revised-attachment-input
                                                                >
                                                            </label>
                                                            <button
                                                                type="button"
                                                                class="grid min-h-16 w-16 shrink-0 place-items-center rounded-lg border border-sky-100 bg-sky-50 text-sky-600 transition hover:border-sky-200 hover:bg-sky-100"
                                                                data-revised-attachment-upload-button
                                                                aria-label="Pilih ulang file revisi"
                                                            >
                                                                <x-flux.icon name="arrow-up-tray" class="size-6" />
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div class="flex flex-wrap items-start gap-2 md:justify-end">
                                                        <button
                                                            type="button"
                                                            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700"
                                                            data-master-attachment-update-button
                                                        >
                                                            Perbarui
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="hidden size-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                                            data-revised-attachment-close
                                                            aria-label="Tutup field perbarui"
                                                        >
                                                            <x-flux.icon name="x-mark" class="size-5" />
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div @class(['mt-5' => $revisionSource && $revisionSourceAttachments->isNotEmpty()])>
                                    <x-documents.attachment-list :existing-files="collect()" />
                                </div>

                                @error('attachments')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                                @error('attachments.*')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                                @error('attachment_titles.*')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                                @error('existing_attachment_titles.*')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                                @error('revised_attachments.*')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </x-documents.form-section>
                </div>

                <aside class="space-y-6 xl:sticky xl:top-8">
                    <section class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                        </div>

                        <div class="space-y-5 px-6 py-6">
                            <x-documents.document-number-input
                                :label="$revisionSource ? 'Nomor Lembar Revisi' : 'Nomor Dokumen'"
                                :prefix="$documentNumberPrefix"
                                :segments="$documentNumberSegments"
                                :default-value="$documentNumberSuffixDefault"
                                :readonly-suffix="(bool) ($revisionSource || $resubmissionSource)"
                            />

                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                                <input
                                    type="text"
                                    value="{{ $nextRevisionValue }}"
                                    readonly
                                    class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-600"
                                >
                            </label>

                            <div class="space-y-4 pt-1 text-base font-medium text-slate-500">
                                <div class="flex items-center gap-3">
                                    <x-flux.icon name="arrow-path" class="size-6 text-slate-700" />
                                    <span>Status</span>
                                    <span class="ml-auto rounded-full bg-slate-200 px-3 py-1 text-sm font-bold text-slate-700">Draft</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <x-flux.icon name="calendar-days" class="size-6 text-slate-700" />
                                    <span>Tanggal Pengajuan</span>
                                    <span class="ml-auto text-slate-500">-</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <x-flux.icon name="calendar" class="size-6 text-slate-700" />
                                    <span>Tanggal Terbit</span>
                                    <span class="ml-auto text-slate-500">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 border-t border-dashed border-slate-200 px-6 py-5 sm:grid-cols-2">
                            <p class="text-center text-xs font-semibold text-slate-500 sm:col-span-2" data-autosave-status>
                                Draft akan tersimpan otomatis saat Anda mengisi form.
                            </p>
                            <button type="submit" name="submit_action" value="draft" formnovalidate data-loading-overlay-skip="true" class="inline-flex h-12 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:bg-slate-50">
                                Simpan Draft
                            </button>
                            <button type="submit" name="submit_action" value="submit" class="inline-flex h-12 items-center justify-center rounded-lg bg-blue-500 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-blue-600">
                                Submit Dokumen
                            </button>
                        </div>
                    </section>
                </aside>
            </form>
        @endif
    </div>

    @once
        <script>
            (() => {
                const AUTOSAVE_DELAY = 1500;
                const autosaveForms = document.querySelectorAll('[data-document-autosave-form]');

                if (autosaveForms.length === 0) {
                    return;
                }

                const setStatus = (form, message, tone = 'muted') => {
                    const status = form.querySelector('[data-autosave-status]');

                    if (!status) {
                        return;
                    }

                    status.textContent = message;
                    status.classList.toggle('text-emerald-600', tone === 'success');
                    status.classList.toggle('text-red-600', tone === 'error');
                    status.classList.toggle('text-slate-500', tone === 'muted');
                };

                const hasMeaningfulPayload = (form) => {
                    const fields = Array.from(form.querySelectorAll('input, select, textarea'))
                        .filter((field) => !['_token', 'draft_id', 'revised_from', 'imported_source'].includes(field.name || ''))
                        .filter((field) => field.type !== 'file')
                        .filter((field) => field.type !== 'hidden' || field.value);

                    return fields.some((field) => {
                        if (field.type === 'checkbox' || field.type === 'radio') {
                            return field.checked;
                        }

                        return String(field.value || '').trim() !== '';
                    }) || Array.from(form.querySelectorAll('input[type="file"]')).some((input) => (input.files || []).length > 0);
                };

                autosaveForms.forEach((form) => {
                    let autosaveTimer = null;
                    let isAutosaving = false;
                    let pendingAutosave = false;
                    let pendingIncludeFiles = false;
                    let activeDraftId = form.querySelector('[data-autosave-draft-id]')?.value || null;

                    const autosavePayload = (includeFiles = false) => {
                        const formData = new FormData();

                        Array.from(form.elements).forEach((field) => {
                            if (!field.name || field.disabled) {
                                return;
                            }

                            if (field.type === 'submit' || field.type === 'button') {
                                return;
                            }

                            if (field.type === 'file') {
                                if (!includeFiles) {
                                    return;
                                }

                                Array.from(field.files || []).forEach((file) => {
                                    formData.append(field.name, file);
                                });

                                return;
                            }

                            if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
                                return;
                            }

                            formData.append(field.name, field.value);
                        });

                        formData.set('submit_action', 'draft');

                        if (activeDraftId) {
                            formData.set('draft_id', activeDraftId);
                        }

                        return formData;
                    };

                    const applyAutosaveResponse = async (response) => {
                        if (!response.ok) {
                            if (response.status === 419) {
                                setStatus(form, 'Sesi form kedaluwarsa. Silakan muat ulang halaman.', 'error');
                                return;
                            }

                            if (response.status === 422) {
                                const errorData = await response.json().catch(() => ({}));
                                const firstError = Object.values(errorData.errors || {})[0]?.[0] || errorData.message || 'Data form belum valid untuk draft.';
                                setStatus(form, `Autosave ditunda: ${firstError}`, 'error');
                                return;
                            }

                            setStatus(form, 'Autosave belum berhasil. Draft manual masih tersedia.', 'error');
                            return;
                        }

                        const payload = await response.json();

                        if (!payload.saved) {
                            return;
                        }

                        if (payload.draft_id) {
                            activeDraftId = String(payload.draft_id);
                            const draftInput = form.querySelector('[data-autosave-draft-id]');
                            if (draftInput) {
                                draftInput.value = activeDraftId;
                            }
                        }

                        setStatus(form, `Draft tersimpan otomatis ${payload.saved_at || ''}`.trim(), 'success');
                    };

                    const autosave = async (includeFiles = false) => {
                        if (form.dataset.autosaveSubmitting === 'true' || !hasMeaningfulPayload(form)) {
                            return;
                        }

                        if (isAutosaving) {
                            pendingAutosave = true;
                            if (includeFiles) {
                                pendingIncludeFiles = true;
                            }
                            return;
                        }

                        isAutosaving = true;
                        setStatus(form, includeFiles ? 'Mengunggah file & menyimpan draft...' : 'Menyimpan draft otomatis...', 'muted');

                        try {
                            const response = await fetch(form.dataset.autosaveUrl, {
                                method: 'POST',
                                body: autosavePayload(includeFiles),
                                credentials: 'same-origin',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                keepalive: !includeFiles,
                            });

                            await applyAutosaveResponse(response);
                        } catch (error) {
                            setStatus(form, 'Autosave belum berhasil terhubung. Perubahan berikutnya akan dicoba lagi.', 'error');
                        } finally {
                            isAutosaving = false;

                            if (pendingAutosave) {
                                pendingAutosave = false;
                                const filesToInclude = pendingIncludeFiles;
                                pendingIncludeFiles = false;
                                autosave(filesToInclude);
                            }
                        }
                    };

                    const autosaveWithBeacon = () => {
                        if (!navigator.sendBeacon || !hasMeaningfulPayload(form)) {
                            return;
                        }

                        navigator.sendBeacon(form.dataset.autosaveUrl, autosavePayload(false));
                    };

                    const scheduleAutosave = () => {
                        window.clearTimeout(autosaveTimer);
                        autosaveTimer = window.setTimeout(() => autosave(false), AUTOSAVE_DELAY);
                    };

                    form.addEventListener('input', (event) => {
                        if (event.target.closest('input[type="file"]')) {
                            return;
                        }

                        scheduleAutosave();
                    });

                    form.addEventListener('change', (event) => {
                        if (event.target.closest('input[type="file"]')) {
                            window.clearTimeout(autosaveTimer);
                            autosave(true);
                            return;
                        }

                        scheduleAutosave();
                    });

                    form.addEventListener('submit', () => {
                        form.dataset.autosaveSubmitting = 'true';
                    });

                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'hidden') {
                            autosaveWithBeacon();
                        }
                    });

                    window.addEventListener('beforeunload', () => {
                        autosaveWithBeacon();
                    });

                    document.addEventListener('click', (event) => {
                        const link = event.target.closest('a[href]');

                        if (link && !link.href.includes('#')) {
                            autosaveWithBeacon();
                        }
                    }, { capture: true });
                });
            })();
        </script>
    @endonce

    @once
        <script>
            (() => {
                const documentNumberSuggestions = @json($documentNumberSuggestions);
                const procedureReferenceNumberSegments = @json($procedureReferenceNumberSegments);

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-document-upload-trigger]');

                    if (!button) {
                        return;
                    }

                    const root = button.closest('[data-document-upload]');
                    const panel = root?.querySelector('[data-document-upload-panel]');

                    if (panel) {
                        panel.classList.remove('hidden');
                    }

                    const fileInput = root?.querySelector('[data-file-upload-input]');
                    if (fileInput) {
                        fileInput.click();
                    }
                });

                document.addEventListener('submit', (event) => {
                    const form = event.target.closest('form');

                    if (!form) {
                        return;
                    }

                    const maxTotalFileSizeKb = Number(form.dataset.maxTotalFileSizeKb || 0);
                    const totalFileSizeError = form.querySelector('[data-total-file-size-error]');

                    if (totalFileSizeError) {
                        totalFileSizeError.textContent = '';
                        totalFileSizeError.classList.add('hidden');
                    }

                    if (maxTotalFileSizeKb > 0) {
                        const selectedFiles = Array.from(form.querySelectorAll('input[type="file"]'))
                            .flatMap((input) => Array.from(input.files || []));
                        const totalFileSize = selectedFiles.reduce((total, file) => total + file.size, 0);

                        if (totalFileSize > maxTotalFileSizeKb * 1024) {
                            event.preventDefault();

                            if (totalFileSizeError) {
                                totalFileSizeError.textContent = `Total ukuran file maksimal ${Math.floor(maxTotalFileSizeKb / 1024)} MB. Kurangi ukuran file atau hapus lampiran tambahan.`;
                                totalFileSizeError.classList.remove('hidden');
                                totalFileSizeError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }

                            return;
                        }
                    }

                    if (event.submitter?.name === 'submit_action' && event.submitter.value === 'draft') {
                        return;
                    }

                    const emptyPicker = Array.from(form.querySelectorAll('[data-user-search-select]'))
                        .find((picker) => {
                            const value = picker.querySelector('[data-user-search-value]');

                            return value?.required && !value.value;
                        });

                    if (!emptyPicker) {
                        return;
                    }

                    event.preventDefault();
                    window.initUserSearchSelect?.(emptyPicker);
                    const trigger = emptyPicker.querySelector('[data-user-search-trigger]');
                    trigger?.focus();
                    trigger?.classList.add('border-red-300', 'ring-2', 'ring-red-100');
                });

                document.addEventListener('change', (event) => {
                    const select = event.target.closest('select[name="m_proses_fungsi_id"]');

                    if (!select) {
                        return;
                    }

                    const form = select.closest('form');
                    const segment = form?.querySelector('[data-document-number-segment="business-function"]');
                    const selectedOption = select.selectedOptions[0];
                    const functionCode = selectedOption?.dataset.functionCode;

                    if (segment) {
                        segment.value = functionCode || 'XXX';
                    }
                });

                const syncProcedureReferenceOptions = (form) => {
                    const processSelect = form.querySelector('select[name="m_proses_bisnis_id"]');
                    const functionSelect = form.querySelector('select[name="m_proses_fungsi_id"]');
                    const referenceSelect = form.querySelector('select[name="reference"]');

                    if (!processSelect || !functionSelect || !referenceSelect) {
                        return;
                    }

                    const processId = processSelect.value;
                    const functionId = functionSelect.value;
                    let selectedReferenceStillValid = referenceSelect.value === '';

                    Array.from(referenceSelect.options).forEach((option) => {
                        if (!option.value) {
                            option.hidden = false;

                            return;
                        }

                        const matches = processId !== ''
                            && option.dataset.businessProcessId === processId
                            && (
                                functionId === ''
                                || option.dataset.businessFunctionId === functionId
                            );

                        option.hidden = !matches;
                        option.disabled = !matches;

                        if (option.selected && matches) {
                            selectedReferenceStillValid = true;
                        }
                    });

                    referenceSelect.disabled = processId === '';

                    if (!selectedReferenceStillValid) {
                        referenceSelect.value = '';
                    }

                    syncProcedureReferenceNumberSegments(form);
                };

                const syncProcedureReferenceNumberSegments = (form) => {
                    const referenceSelect = form.querySelector('select[name="reference"]');

                    if (!referenceSelect) {
                        return;
                    }

                    const segments = procedureReferenceNumberSegments[referenceSelect.value] || ['XXX', 'YY'];

                    form.querySelectorAll('[data-document-number-segment^="procedure-reference-"]').forEach((input, index) => {
                        input.value = segments[index] || (index === 0 ? 'XXX' : 'YY');
                    });
                };

                const syncDocumentNumberSuggestion = (form) => {
                    const processSelect = form.querySelector('select[name="m_proses_bisnis_id"]');
                    const functionSelect = form.querySelector('select[name="m_proses_fungsi_id"]');
                    const suffixInput = form.querySelector('input[name="nomor_dokumen_suffix"]');

                    if (!processSelect || !functionSelect || !suffixInput) {
                        return;
                    }

                    if (suffixInput.readOnly || suffixInput.dataset.userEdited === 'true') {
                        return;
                    }

                    const functionId = functionSelect.value;

                    if (functionId === '') {
                        return;
                    }

                    const numberInputs = Array.from(suffixInput.closest('.grid')?.querySelectorAll('input') || []);
                    const scope = numberInputs
                        .slice(0, -1)
                        .map((input) => input.value.trim())
                        .filter(Boolean)
                        .join('-');

                    suffixInput.value = documentNumberSuggestions[`scope:${scope}`] ?? '01';
                };

                document.querySelectorAll('form').forEach((form) => {
                    syncProcedureReferenceOptions(form);
                    syncDocumentNumberSuggestion(form);
                });

                document.addEventListener('input', (event) => {
                    const revisionNameInput = event.target.closest('[data-revision-name-input]');

                    if (revisionNameInput) {
                        const root = revisionNameInput.closest('[data-revision-name-editor]');
                        const display = root?.querySelector('[data-revision-name-display]');

                        if (display) {
                            display.textContent = revisionNameInput.value || '-';
                        }
                    }

                    const suffixInput = event.target.closest('input[name="nomor_dokumen_suffix"]');

                    if (suffixInput && !suffixInput.readOnly) {
                        suffixInput.dataset.userEdited = 'true';
                    }
                });

                document.addEventListener('click', (event) => {
                    const toggle = event.target.closest('[data-revision-name-edit-toggle]');

                    if (!toggle) {
                        return;
                    }

                    const root = toggle.closest('[data-revision-name-editor]');
                    const display = root?.querySelector('[data-revision-name-display]');
                    const inputWrap = root?.querySelector('[data-revision-name-input-wrap]');
                    const input = root?.querySelector('[data-revision-name-input]');
                    const editIcon = toggle.querySelector('[data-revision-name-edit-icon]');
                    const cancelIcon = toggle.querySelector('[data-revision-name-cancel-icon]');
                    const saveButton = root?.querySelector('[data-revision-name-save]');
                    const isEditing = toggle.dataset.editing === 'true';

                    if (!root || !display || !inputWrap || !input) {
                        return;
                    }

                    if (isEditing) {
                        const initialName = root.dataset.initialName || '';

                        input.value = initialName;
                        display.textContent = initialName || '-';
                        display.classList.remove('hidden');
                        inputWrap.classList.add('hidden');
                        toggle.dataset.editing = 'false';
                        toggle.setAttribute('aria-label', 'Ubah nama dokumen');
                        toggle.classList.remove('border-orange-200', 'bg-orange-50', 'text-orange-600');
                        editIcon?.classList.remove('hidden');
                        cancelIcon?.classList.add('hidden');
                        saveButton?.classList.add('hidden');
                        if (saveButton) {
                            saveButton.style.display = 'none';
                        }

                        return;
                    }

                    display.classList.add('hidden');
                    inputWrap.classList.remove('hidden');
                    toggle.dataset.editing = 'true';
                    toggle.setAttribute('aria-label', 'Batal ubah nama dokumen');
                    toggle.classList.add('border-orange-200', 'bg-orange-50', 'text-orange-600');
                    editIcon?.classList.add('hidden');
                    cancelIcon?.classList.remove('hidden');
                    saveButton?.classList.remove('hidden');
                    if (saveButton) {
                        saveButton.style.display = '';
                    }
                    input.focus();
                    input.select();
                });

                document.addEventListener('click', (event) => {
                    const saveButton = event.target.closest('[data-revision-name-save]');

                    if (!saveButton) {
                        return;
                    }

                    const root = saveButton.closest('[data-revision-name-editor]');
                    const toggle = root?.querySelector('[data-revision-name-edit-toggle]');
                    const display = root?.querySelector('[data-revision-name-display]');
                    const inputWrap = root?.querySelector('[data-revision-name-input-wrap]');
                    const input = root?.querySelector('[data-revision-name-input]');
                    const editIcon = toggle?.querySelector('[data-revision-name-edit-icon]');
                    const cancelIcon = toggle?.querySelector('[data-revision-name-cancel-icon]');
                    const savedName = input?.value || '';

                    if (!root || !toggle || !display || !inputWrap || !input) {
                        return;
                    }

                    root.dataset.initialName = savedName;
                    display.textContent = savedName || '-';
                    display.classList.remove('hidden');
                    inputWrap.classList.add('hidden');
                    toggle.dataset.editing = 'false';
                    toggle.setAttribute('aria-label', 'Ubah nama dokumen');
                    toggle.classList.remove('border-orange-200', 'bg-orange-50', 'text-orange-600');
                    editIcon?.classList.remove('hidden');
                    cancelIcon?.classList.add('hidden');
                    saveButton.classList.add('hidden');
                    saveButton.style.display = 'none';
                });

                document.addEventListener('change', (event) => {
                    if (!event.target.closest('select[name="m_proses_bisnis_id"], select[name="m_proses_fungsi_id"]')) {
                        return;
                    }

                    const form = event.target.closest('form');

                    if (form) {
                        syncProcedureReferenceOptions(form);
                        syncDocumentNumberSuggestion(form);
                    }
                });

                document.addEventListener('change', (event) => {
                    if (!event.target.closest('select[name="reference"]')) {
                        return;
                    }

                    const form = event.target.closest('form');

                    if (form) {
                        syncProcedureReferenceNumberSegments(form);
                        syncDocumentNumberSuggestion(form);
                    }
                });
            })();
        </script>
    @endonce

    @once
        <script>
            (() => {
                const syncOfficialPreparer = (root, user, sourceLabel) => {
                    const input = root.querySelector('[data-official-preparer-picker] [data-user-search-value]');
                    const card = root.querySelector('[data-official-preparer-card]');
                    const empty = root.querySelector('[data-official-preparer-empty]');
                    const initials = root.querySelector('[data-official-preparer-initials]');
                    const name = root.querySelector('[data-official-preparer-name]');
                    const meta = root.querySelector('[data-official-preparer-meta]');
                    const source = root.querySelector('[data-official-preparer-source]');

                    if (!input || !card || !empty || !initials || !name || !meta || !source) {
                        return;
                    }

                    input.value = user.id || '';
                    initials.textContent = user.initials || '-';
                    name.textContent = user.name || '-';
                    meta.textContent = user.title || user.email || '-';
                    source.textContent = sourceLabel;
                    card.classList.remove('hidden');
                    empty.classList.add('hidden');
                };

                document.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-use-current-user]');

                    if (!button) {
                        return;
                    }

                    const root = button.closest('[data-official-preparer]');
                    const picker = root?.querySelector('[data-official-preparer-picker]');

                    window.setUserSearchSelect?.(picker, {
                        id: button.dataset.userId,
                        name: button.dataset.userName,
                        email: button.dataset.userEmail,
                        title: button.dataset.userTitle,
                        meta: button.dataset.userTitle || button.dataset.userEmail,
                        initials: button.dataset.userInitials,
                    });

                    syncOfficialPreparer(root, {
                        id: button.dataset.userId,
                        name: button.dataset.userName,
                        email: button.dataset.userEmail,
                        title: button.dataset.userTitle,
                        initials: button.dataset.userInitials,
                    }, 'Tanpa perwakilan');
                });

                document.addEventListener('user-search-select:selected', (event) => {
                    const picker = event.target.closest('[data-official-preparer-picker]');

                    if (!picker) {
                        return;
                    }

                    const root = picker.closest('[data-official-preparer]');
                    const user = event.detail;

                    syncOfficialPreparer(root, {
                        id: user.value,
                        name: user.name,
                        email: user.email,
                        title: user.title,
                        initials: user.initials,
                    }, 'Diwakilkan');
                });
            })();
        </script>
    @endonce

    @once
        <script>
            (() => {
                const formatFileSize = (file) => {
                    const sizeKb = Math.ceil(file.size / 1024);

                    return sizeKb >= 1024
                        ? `${(sizeKb / 1024).toFixed(1)} MB`
                        : `${sizeKb} KB`;
                };

                const setIncluded = (row, included) => {
                    const input = row?.querySelector('[data-master-attachment-include]');
                    const checkbox = row?.querySelector('[data-master-attachment-checkbox]');
                    const badge = row?.querySelector('[data-master-attachment-badge]');

                    if (!row || !input || !checkbox || !badge) {
                        return;
                    }

                    row.dataset.included = included ? 'true' : 'false';
                    input.disabled = !included;
                    checkbox.checked = included;
                    badge.textContent = included ? 'Dicantumkan' : 'Tidak Dicantumkan';
                    badge.className = included
                        ? 'inline-flex min-w-32 justify-center whitespace-nowrap rounded-full bg-emerald-50 px-3 py-1 text-center text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-100'
                        : 'inline-flex min-w-32 justify-center whitespace-nowrap rounded-full bg-red-50 px-3 py-1 text-center text-[11px] font-bold text-red-700 ring-1 ring-red-100';
                };

                const pickerForRow = (row) => row?.querySelector('[data-revised-attachment-file]');

                const resetPicker = (picker) => {
                    const input = picker?.querySelector('[data-revised-attachment-input]');
                    const name = picker?.querySelector('[data-revised-attachment-name]');
                    const meta = picker?.querySelector('[data-revised-attachment-meta]');
                    const icon = picker?.querySelector('[data-revised-attachment-icon]');
                    const row = picker?.closest('[data-master-attachment-row]');
                    const closeButton = row?.querySelector('[data-revised-attachment-close]');

                    if (!picker || !input || !name || !meta || !icon || !row || !closeButton) {
                        return;
                    }

                    input.value = '';
                    picker.classList.add('hidden');
                    picker.classList.remove('grid');
                    closeButton.classList.add('hidden');
                    closeButton.classList.remove('inline-flex');
                    name.textContent = 'Pilih file PDF';
                    meta.textContent = 'Maksimal 10 MB';
                    icon.className = 'grid size-12 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500';
                    icon.innerHTML = '<svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 7.5 12 3m0 0 4.5 4.5M12 3v13.5" /></svg>';
                };

                const showSourceFile = (picker) => {
                    const name = picker?.querySelector('[data-revised-attachment-name]');
                    const meta = picker?.querySelector('[data-revised-attachment-meta]');
                    const icon = picker?.querySelector('[data-revised-attachment-icon]');
                    const sourceFileName = picker?.dataset.sourceFileName || 'File master sebelumnya';

                    if (!picker || !name || !meta || !icon) {
                        return;
                    }

                    picker.classList.remove('hidden');
                    picker.classList.add('grid');
                    name.textContent = sourceFileName;
                    meta.textContent = 'File master sebelumnya';
                    icon.className = 'grid size-12 shrink-0 place-items-center rounded-lg border border-red-100 bg-red-50 text-xs font-bold text-red-600';
                    icon.textContent = 'PDF';
                };

                document.addEventListener('change', (event) => {
                    const checkbox = event.target.closest('[data-master-attachment-checkbox]');

                    if (checkbox) {
                        const row = checkbox.closest('[data-master-attachment-row]');
                        setIncluded(row, checkbox.checked);

                        if (!checkbox.checked) {
                            resetPicker(pickerForRow(row));
                        }

                        return;
                    }

                    const input = event.target.closest('[data-revised-attachment-input]');

                    if (!input) {
                        return;
                    }

                    const picker = input.closest('[data-revised-attachment-file]');
                    const row = input.closest('[data-master-attachment-row]');
                    const file = input.files?.[0];

                    if (!file) {
                        resetPicker(picker);

                        return;
                    }

                    setIncluded(row, true);

                    const name = picker?.querySelector('[data-revised-attachment-name]');
                    const meta = picker?.querySelector('[data-revised-attachment-meta]');
                    const icon = picker?.querySelector('[data-revised-attachment-icon]');

                    if (!name || !meta || !icon) {
                        return;
                    }

                    name.textContent = file.name;
                    meta.textContent = formatFileSize(file);
                    icon.className = 'grid size-12 shrink-0 place-items-center rounded-lg border border-red-100 bg-red-50 text-xs font-bold text-red-600';
                    icon.textContent = 'PDF';
                });

                document.addEventListener('click', (event) => {
                    const updateButton = event.target.closest('[data-master-attachment-update-button]');

                    if (updateButton) {
                        const row = updateButton.closest('[data-master-attachment-row]');
                        const picker = pickerForRow(row);
                        const closeButton = row?.querySelector('[data-revised-attachment-close]');

                        setIncluded(row, true);
                        showSourceFile(picker);
                        closeButton?.classList.remove('hidden');
                        closeButton?.classList.add('inline-flex');
                        picker?.querySelector('[data-revised-attachment-input]')?.click();

                        return;
                    }

                    const uploadButton = event.target.closest('[data-revised-attachment-upload-button]');

                    if (uploadButton) {
                        uploadButton.closest('[data-revised-attachment-file]')?.querySelector('[data-revised-attachment-input]')?.click();

                        return;
                    }

                    const closeButton = event.target.closest('[data-revised-attachment-close]');

                    if (closeButton) {
                        resetPicker(pickerForRow(closeButton.closest('[data-master-attachment-row]')));
                    }
                });

                document.querySelectorAll('[data-master-attachment-row]').forEach((row) => {
                    setIncluded(row, row.dataset.included === 'true');
                });
            })();
        </script>
    @endonce

    <x-ui.loading-overlay />
</x-layouts.app>
