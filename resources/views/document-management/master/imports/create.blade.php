@php
    $levelNumbers = [
        'level-1' => 'I',
        'level-2' => 'II',
        'level-3' => 'III',
    ];
    $documentTitle = \Illuminate\Support\Str::after($levelConfig['name'], ': ');
    $levelDisplayValue = $documentLevelRecord
        ? $documentLevelRecord->nama_level.' : '.\Illuminate\Support\Str::after($documentLevelRecord->nama_dokumen, ': ')
        : $levelConfig['badge'].' : '.$documentTitle;
    $selectedBusinessProcessId = old('m_proses_bisnis_id');
    $selectedBusinessFunctionId = old('m_proses_fungsi_id');
    $selectedDepartmentIds = $selectedDepartmentIds ?? [];
    $documentState ??= \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::STATE_MASTER;
    $isObsoleteImport = $documentState === \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::STATE_OBSOLETE;
    $pageTitle = $isObsoleteImport ? 'Import Dokumen Obsolete' : 'Import Dokumen Master';
    $sectionTitle = $isObsoleteImport ? 'Dokumen Obsolete' : 'Dokumen Master';
    $indexRoute = $isObsoleteImport ? route('documents.obsolete.imports.create') : route('documents.master.imports.create');
    $storeRoute = $isObsoleteImport
        ? route('documents.obsolete.imports.store.level', $level)
        : route('documents.master.imports.store.level', $level);
    $mainFileName = $isObsoleteImport ? 'obsolete_document' : 'existing_document';
    $mainFileLabel = $isObsoleteImport ? 'File Dokumen Obsolete' : 'Dokumen Master Import';

    $documentPrefixes = [
        'level-1' => 'SM',
        'level-2' => 'PS',
        'level-3' => 'IK',
    ];
    $documentNumberPrefix = $documentPrefixes[$level] ?? 'DOC';
    $selectedBusinessFunction = isset($businessFunctions)
        ? $businessFunctions->firstWhere('id', (int) $selectedBusinessFunctionId)
        : null;
    $documentNumberFunctionCode = $selectedBusinessFunction?->kode ?: 'XXX';
    $selectedReference = old('reference');
    $procedureReferenceNumberSegments = $procedureReferenceNumberSegments ?? [];
    $selectedProcedureSegments = collect($procedureReferenceNumberSegments[$selectedReference] ?? ['XXX', 'YY']);

    $documentNumberSegments = match ($level) {
        'level-2' => [['value' => $documentNumberFunctionCode, 'target' => 'business-function']],
        'level-3' => [
            ['value' => $selectedProcedureSegments->get(0, 'XXX'), 'target' => 'procedure-reference-0'],
            ['value' => $selectedProcedureSegments->get(1, 'YY'), 'target' => 'procedure-reference-1'],
        ],
        default => [],
    };
@endphp

<x-layouts.app :title="__($pageTitle)">

    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700">Home</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ $isObsoleteImport ? route('documents.obsolete') : route('documents.master') }}" class="transition hover:text-sky-700">{{ $sectionTitle }}</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ $indexRoute }}" class="transition hover:text-sky-700">{{ $pageTitle }}</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $levelConfig['badge'] }}</span>
        </nav>

        <div>
            <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                Import Dokumen {{ $isObsoleteImport ? 'Obsolete Level' : 'Level' }} {{ $levelNumbers[$level] }} : {{ $documentTitle }}
            </h1>
        </div>

        <form
            method="POST"
            action="{{ $storeRoute }}"
            enctype="multipart/form-data"
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]"
            @unless ($isObsoleteImport)
                data-import-master-number-check-url="{{ route('documents.existing.imports.number-reuse-check') }}"
            @endunless
        >
            @csrf
            <input type="hidden" name="document_state" value="{{ $documentState }}">
            <input type="hidden" name="obsolete_rule_type" value="{{ \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::CURRENT_RULE }}">
            <input type="hidden" name="confirm_imported_master_number_reuse" value="0">

            <div class="space-y-6">
                @if ($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                        <p>Import dokumen belum berhasil. Cek isian berikut:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Informasi Dokumen --}}
                <x-documents.form-section title="Informasi Dokumen">
                    <div class="grid gap-5 px-6 py-6 md:grid-cols-2">
                        <label class="block md:col-span-2">
                            <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                            <input
                                type="text"
                                name="nama_dokumen"
                                value="{{ old('nama_dokumen') }}"
                                placeholder="Masukkan nama dokumen"
                                required
                                @class([
                                    'h-14 w-full rounded-lg bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:ring-2',
                                    'border border-red-300 focus:border-red-400 focus:ring-red-100' => $errors->has('nama_dokumen'),
                                    'border border-slate-300 focus:border-sky-400 focus:ring-sky-100' => ! $errors->has('nama_dokumen'),
                                ])
                            >
                        </label>

                        <input type="hidden" name="m_document_level_id" value="{{ $documentLevelRecord?->id }}">

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Proses Bisnis</span>
                            <select
                                name="m_proses_bisnis_id"
                                required
                                class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >
                                @foreach ($processOptions as $value => $label)
                                    <option value="{{ $value }}" @selected((string) $selectedBusinessProcessId === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('m_proses_bisnis_id')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Proses / Fungsi</span>
                            <select
                                name="m_proses_fungsi_id"
                                required
                                class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >
                                <option value="">Pilih Proses Fungsi</option>
                                @if (isset($businessFunctions) && $businessFunctions->isNotEmpty())
                                    @foreach ($businessFunctions as $func)
                                        <option
                                            value="{{ $func->id }}"
                                            data-function-code="{{ $func->kode }}"
                                            data-business-process-id="{{ $func->m_proses_bisnis_id }}"
                                            @selected((string) $selectedBusinessFunctionId === (string) $func->id)
                                        >
                                            {{ $func->nama_proses_fungsi }}
                                        </option>
                                    @endforeach
                                @else
                                    @foreach ($functionOptions as $value => $label)
                                        <option value="{{ $value }}" @selected((string) $selectedBusinessFunctionId === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                @endif
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
                        />

                        @if ($level === 'level-3')
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Pilih Dokumen Level II: Prosedur</span>
                                <select
                                    name="reference"
                                    required
                                    class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >
                                    <option value="">-Pilih-</option>
                                    @if ($importedProcedures->isNotEmpty())
                                        <optgroup label="Prosedur Existing / Imported">
                                            @foreach ($importedProcedures as $proc)
                                                <option
                                                    value="imported-{{ $proc->id }}"
                                                    data-business-process-id="{{ $proc->m_proses_bisnis_id }}"
                                                    data-business-function-id="{{ $proc->m_proses_fungsi_id }}"
                                                    @selected((string) old('reference') === "imported-{$proc->id}")
                                                >
                                                    {{ $proc->nomor_dokumen ?: '-' }} - {{ $proc->nama_dokumen }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if ($workflowProcedures->isNotEmpty())
                                        <optgroup label="Prosedur Master V2">
                                            @foreach ($workflowProcedures as $proc)
                                                <option
                                                    value="existing-{{ $proc->id }}"
                                                    data-business-process-id="{{ $proc->m_proses_bisnis_id }}"
                                                    data-business-function-id="{{ $proc->m_proses_fungsi_id }}"
                                                    @selected((string) old('reference') === "existing-{$proc->id}")
                                                >
                                                    {{ $proc->nomor_dokumen ?: '-' }} - {{ $proc->nama_dokumen }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                                @error('reference')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>
                        @endif
                    </div>
                </x-documents.form-section>

                {{-- Isi Dokumen --}}
                <x-documents.form-section title="Isi Dokumen" icon="cloud-arrow-up">
                    <div class="space-y-6 px-6 py-6">
                        <x-ui.file-upload
                            label="{{ $mainFileLabel }}"
                            name="{{ $mainFileName }}"
                            accept=".pdf,.doc,.docx,.xls,.xlsx"
                            hint="Format PDF, Word, atau Excel. Maks 10 MB."
                            :max-files="1"
                            :max-file-size-kb="10240"
                            required
                        />
                        @error($mainFileName)
                            <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                </x-documents.form-section>

                @if ($isObsoleteImport)
                    <x-documents.form-section title="Dokumen Pengganti" icon="link">
                        <div class="px-6 py-6">
                            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Digantikan Oleh</span>
                            <x-ui.document-search-select
                                name="replacement_reference"
                                :documents="$relationDocumentOptions"
                                :value="old('replacement_reference')"
                                placeholder="Belum ditentukan"
                                empty-label="Dokumen tidak ditemukan."
                                :filter-by-context="true"
                            />
                            @error('replacement_reference')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </div>
                    </x-documents.form-section>
                @endif
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-6 xl:sticky xl:top-8">
                <section class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                    </div>

                    <div class="space-y-5 px-6 py-6">
                        <x-documents.document-number-input
                            :prefix="$documentNumberPrefix"
                            :segments="$documentNumberSegments"
                            :default-value="old('nomor_dokumen_suffix')"
                            label="Nomor Dokumen"
                        />

                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Revisi</span>
                            <input
                                type="text"
                                name="nomor_revisi"
                                value="{{ old('nomor_revisi') }}"
                                placeholder="__.__"
                                inputmode="numeric"
                                pattern="\d{2}\.\d{2}"
                                title="Gunakan format 00.00"
                                autocomplete="off"
                                required
                                data-import-master-revision-mask
                                @class([
                                    'h-14 w-full rounded-lg bg-white px-4 font-mono text-base font-semibold tracking-normal outline-none transition placeholder:text-slate-400',
                                    'border border-red-300 text-red-700 focus:border-red-400 focus:ring-2 focus:ring-red-100' => $errors->has('nomor_revisi'),
                                    'border border-slate-300 text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-100' => ! $errors->has('nomor_revisi'),
                                ])
                            >
                            @error('nomor_revisi')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </div>

                        <x-ui.date-input
                            label="Tanggal Terbit"
                            name="tanggal_terbit"
                            :value="old('tanggal_terbit')"
                        />
                        @error('tanggal_terbit')
                            <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror

                        @if ($isObsoleteImport)
                            <x-ui.date-input
                                label="Tanggal Obsolete"
                                name="tanggal_obsolete"
                                :value="old('tanggal_obsolete')"
                            />
                            @error('tanggal_obsolete')
                                <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        @endif

                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Catatan</span>
                            <textarea
                                name="catatan"
                                rows="4"
                                placeholder="{{ $isObsoleteImport ? 'Catatan bebas terkait arsip obsolete ini.' : 'Catatan bebas terkait imported master ini.' }}"
                                class="w-full resize-none rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >{{ old('catatan') }}</textarea>
                        </div>

                        <div class="space-y-3 text-sm font-medium text-slate-500">
                            <div class="flex items-center gap-3">
                                <x-flux.icon name="document-check" class="size-5 text-slate-700" />
                                <span>Level</span>
                                <span class="ml-auto rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-700">{{ $levelConfig['badge'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-flux.icon name="arrow-path" class="size-5 text-slate-700" />
                                <span>Status</span>
                                <span @class([
                                    'ml-auto rounded-full px-3 py-1 text-xs font-bold',
                                    'bg-amber-100 text-amber-700' => $isObsoleteImport,
                                    'bg-emerald-100 text-emerald-700' => ! $isObsoleteImport,
                                ])>{{ $isObsoleteImport ? 'Obsolete' : 'Master' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-slate-200 px-6 py-5">
                        <div class="flex gap-3">
                            <a
                                href="{{ $indexRoute }}"
                                class="inline-flex h-12 flex-1 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:bg-slate-50"
                            >
                                Batal
                            </a>
                            <button
                                type="submit"
                                class="inline-flex h-12 flex-1 items-center justify-center rounded-lg bg-sky-600 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-sky-700"
                            >
                                {{ $isObsoleteImport ? 'Simpan Obsolete' : 'Simpan Master' }}
                            </button>
                        </div>
                    </div>
                </section>
            </aside>
        </form>
    </div>

    <script>
        (() => {
            // Sync filter procedure reference by process & function
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

                // Loop options & optgroups
                Array.from(referenceSelect.querySelectorAll('option')).forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    const matches = processId !== ''
                        && option.dataset.businessProcessId === processId
                        && (functionId === '' || option.dataset.businessFunctionId === functionId);

                    option.hidden = !matches;
                    option.disabled = !matches;

                    if (option.selected && matches) {
                        selectedReferenceStillValid = true;
                    }
                });

                // Toggle visibility of optgroups
                Array.from(referenceSelect.querySelectorAll('optgroup')).forEach((group) => {
                    const visibleOptions = Array.from(group.querySelectorAll('option')).filter(opt => !opt.hidden);
                    group.hidden = visibleOptions.length === 0;
                });

                referenceSelect.disabled = processId === '';

                if (!selectedReferenceStillValid) {
                    referenceSelect.value = '';
                }

                syncProcedureReferenceNumberSegments(form);
            };

            const procedureReferenceNumberSegments = @json($procedureReferenceNumberSegments);

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

            const syncDocumentNumberFunctionSegment = (form) => {
                const functionSelect = form.querySelector('select[name="m_proses_fungsi_id"]');
                const segment = form.querySelector('[data-document-number-segment="business-function"]');
                if (!functionSelect || !segment) {
                    return;
                }
                const selectedOption = functionSelect.selectedOptions[0];
                const functionCode = selectedOption?.dataset.functionCode;
                segment.value = functionCode || 'XXX';
            };

            const closeDocumentSearch = (root) => {
                root?.querySelector('[data-document-search-panel]')?.classList.add('hidden');
                root?.querySelector('[data-document-search-trigger]')?.setAttribute('aria-expanded', 'false');
            };

            const clearDocumentSearch = (root) => {
                root.querySelector('[data-document-search-value]').value = '';
                root.querySelector('[data-document-search-name]').textContent = root.dataset.placeholder || 'Pilih dokumen';
                const meta = root.querySelector('[data-document-search-meta]');
                meta.textContent = '';
                meta.classList.add('hidden');
            };

            const setDocumentSearch = (root, option) => {
                const value = root.querySelector('[data-document-search-value]');
                const meta = root.querySelector('[data-document-search-meta]');

                value.value = option.dataset.value || '';
                root.querySelector('[data-document-search-name]').textContent = option.dataset.name || root.dataset.placeholder || 'Pilih dokumen';
                meta.textContent = option.dataset.meta || '';
                meta.classList.toggle('hidden', !meta.textContent);
                value.dispatchEvent(new Event('change', { bubbles: true }));
            };

            const syncDocumentSearchOptions = (form) => {
                form.querySelectorAll('[data-document-search-select]').forEach((root) => {
                    const processSelect = form.querySelector('select[name="m_proses_bisnis_id"]');
                    const functionSelect = form.querySelector('select[name="m_proses_fungsi_id"]');
                    const levelInput = form.querySelector('[name="m_document_level_id"]');
                    const input = root.querySelector('[data-document-search-input]');
                    const value = root.querySelector('[data-document-search-value]');
                    const trigger = root.querySelector('[data-document-search-trigger]');
                    const query = (input?.value || '').trim().toLowerCase();
                    const levelId = levelInput?.value || '';
                    const processId = processSelect?.value || '';
                    const functionId = functionSelect?.value || '';
                    const filterByContext = root.dataset.filterByContext === 'true';
                    let visibleCount = 0;
                    let selectedStillVisible = value.value === '';

                    root.querySelectorAll('[data-document-search-option]').forEach((option) => {
                        const matchesContext = !filterByContext
                            || (levelId !== ''
                                && processId !== ''
                                && functionId !== ''
                                && option.dataset.documentLevelId === levelId
                                && option.dataset.businessProcessId === processId
                                && option.dataset.businessFunctionId === functionId);
                        const matchesSearch = query === '' || (option.dataset.search || '').includes(query);
                        const isVisible = matchesContext && matchesSearch;

                        option.classList.toggle('hidden', !isVisible);
                        visibleCount += isVisible ? 1 : 0;

                        if (value.value !== '' && option.dataset.value === value.value && isVisible) {
                            selectedStillVisible = true;
                        }
                    });

                    root.querySelector('[data-document-search-empty]')?.classList.toggle('hidden', visibleCount > 0);
                    trigger.disabled = filterByContext && (levelId === '' || processId === '' || functionId === '');

                    if (!selectedStillVisible) {
                        clearDocumentSearch(root);
                    }
                });
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            const checkImportedMasterNumberReuse = async (form) => {
                const url = form.dataset.importMasterNumberCheckUrl;

                if (!url) {
                    return true;
                }

                const confirmationInput = form.querySelector('input[name="confirm_imported_master_number_reuse"]');

                if (confirmationInput?.value === '1') {
                    return true;
                }

                const payloadData = new FormData();
                [
                    'document_state',
                    'obsolete_rule_type',
                    'm_document_level_id',
                    'm_proses_bisnis_id',
                    'm_proses_fungsi_id',
                    'reference',
                    'nomor_dokumen',
                    'nomor_dokumen_suffix',
                ].forEach((name) => {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field) {
                        payloadData.append(name, field.value || '');
                    }
                });

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: payloadData,
                });

                if (!response.ok) {
                    return true;
                }

                const payload = await response.json();

                if (!payload.conflict) {
                    return true;
                }

                if (payload.blocked) {
                    window.alert(payload.message || 'Nomor dokumen sudah digunakan oleh dokumen master.');

                    return false;
                }

                if (window.confirm(payload.message || 'Nomor dokumen sudah digunakan. Apakah Anda yakin ingin melanjutkan?')) {
                    if (confirmationInput) {
                        confirmationInput.value = '1';
                    }

                    return true;
                }

                return false;
            };

            document.querySelectorAll('form').forEach((form) => {
                syncProcedureReferenceOptions(form);
                syncDocumentSearchOptions(form);
                syncDocumentNumberFunctionSegment(form);
                syncProcedureReferenceNumberSegments(form);

                form.addEventListener('submit', async (event) => {
                    const suffixInput = form.querySelector('input[name="nomor_dokumen_suffix"]');
                    if (suffixInput) {
                        const val = suffixInput.value.trim();
                        if (/^\d{1}$/.test(val)) {
                            suffixInput.value = val.padStart(2, '0');
                        }
                    }

                    if (!form.dataset.importMasterNumberCheckUrl) {
                        return;
                    }

                    if (form.dataset.importMasterNumberCheckPassed === 'true') {
                        delete form.dataset.importMasterNumberCheckPassed;

                        return;
                    }

                    if (form.querySelector('input[name="confirm_imported_master_number_reuse"]')?.value === '1') {
                        return;
                    }

                    event.preventDefault();

                    if (await checkImportedMasterNumberReuse(form)) {
                        form.dataset.importMasterNumberCheckPassed = 'true';
                        if (event.submitter) {
                            form.requestSubmit(event.submitter);
                        } else {
                            form.requestSubmit();
                        }
                    }
                });
            });

            document.addEventListener('change', (event) => {
                const form = event.target.closest('form');
                if (!form) {
                    return;
                }

                if (event.target.closest('select[name="m_proses_bisnis_id"], select[name="m_proses_fungsi_id"]')) {
                    syncProcedureReferenceOptions(form);
                    syncDocumentSearchOptions(form);
                    syncDocumentNumberFunctionSegment(form);
                }

                if (event.target.closest('select[name="reference"]')) {
                    syncProcedureReferenceNumberSegments(form);
                }
            });

            document.addEventListener('blur', (event) => {
                const suffixInput = event.target.closest('input[name="nomor_dokumen_suffix"]');
                if (suffixInput) {
                    const val = suffixInput.value.trim();
                    if (/^\d{1}$/.test(val)) {
                        suffixInput.value = val.padStart(2, '0');
                    }
                }
            }, true);

            document.addEventListener('input', (event) => {
                if (!event.target.closest('[data-document-search-input]')) {
                    return;
                }

                const form = event.target.closest('form');
                if (form) {
                    syncDocumentSearchOptions(form);
                }
            });

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-document-search-trigger]');

                if (trigger) {
                    const root = trigger.closest('[data-document-search-select]');
                    document.querySelectorAll('[data-document-search-select]').forEach((picker) => {
                        if (picker !== root) {
                            closeDocumentSearch(picker);
                        }
                    });
                    root?.querySelector('[data-document-search-panel]')?.classList.toggle('hidden');
                    trigger.setAttribute('aria-expanded', String(!root?.querySelector('[data-document-search-panel]')?.classList.contains('hidden')));
                    root?.querySelector('[data-document-search-input]')?.focus();
                    return;
                }

                const option = event.target.closest('[data-document-search-option]');

                if (option && !option.classList.contains('hidden')) {
                    const root = option.closest('[data-document-search-select]');
                    setDocumentSearch(root, option);
                    closeDocumentSearch(root);
                    return;
                }

                document.querySelectorAll('[data-document-search-select]').forEach((root) => {
                    if (!root.contains(event.target)) {
                        closeDocumentSearch(root);
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    document.querySelectorAll('[data-document-search-select]').forEach(closeDocumentSearch);
                }
            });

            const revisionPattern = /^\d{2}\.\d{2}$/;
            const revisionPlaceholder = '__.__';

            const revisionDigits = (value) => value.replace(/\D/g, '').slice(0, 4);
            const revisionPositionForDigitCount = (digitCount) => {
                if (digitCount <= 0) {
                    return 0;
                }

                if (digitCount <= 2) {
                    return digitCount;
                }

                return digitCount + 1;
            };

            const revisionDigitCountBeforePosition = (value, position) => {
                return revisionDigits(value.slice(0, position)).length;
            };

            const formatRevision = (value, padded = true) => {
                const digits = revisionDigits(value);
                const chars = revisionPlaceholder.split('');
                let digitIndex = 0;

                for (let index = 0; index < chars.length && digitIndex < digits.length; index += 1) {
                    if (chars[index] === '_') {
                        chars[index] = digits[digitIndex];
                        digitIndex += 1;
                    }
                }

                if (padded) {
                    return chars.join('');
                }

                return digits.length > 2
                    ? `${digits.slice(0, 2)}.${digits.slice(2)}`
                    : digits;
            };

            const setRevisionValidity = (input) => {
                input.setCustomValidity(
                    revisionPattern.test(input.value)
                        ? ''
                        : 'Nomor revisi wajib menggunakan format 00.00.'
                );
            };

            const syncRevisionInput = (input, nextDigitPosition = null) => {
                const cursorPosition = input.selectionStart ?? input.value.length;
                const digitPosition = nextDigitPosition ?? revisionDigitCountBeforePosition(input.value, cursorPosition);

                input.value = formatRevision(input.value);
                setRevisionValidity(input);

                window.requestAnimationFrame(() => {
                    const nextCursorPosition = revisionPositionForDigitCount(digitPosition);
                    input.setSelectionRange(nextCursorPosition, nextCursorPosition);
                });
            };

            document.querySelectorAll('[data-import-master-revision-mask]').forEach((input) => {
                syncRevisionInput(input, revisionDigits(input.value).length);

                input.addEventListener('focus', () => {
                    syncRevisionInput(input, revisionDigits(input.value).length);
                });

                input.addEventListener('click', () => {
                    syncRevisionInput(input);
                });

                input.addEventListener('keydown', (event) => {
                    if (!['Backspace', 'Delete'].includes(event.key)) {
                        return;
                    }

                    const cursorStart = input.selectionStart ?? 0;
                    const cursorEnd = input.selectionEnd ?? cursorStart;
                    const digits = revisionDigits(input.value);
                    const digitStart = revisionDigitCountBeforePosition(input.value, cursorStart);
                    const digitEnd = revisionDigitCountBeforePosition(input.value, cursorEnd);

                    let nextDigits = digits;
                    let nextDigitPosition = digitStart;

                    if (cursorStart !== cursorEnd && digitStart !== digitEnd) {
                        nextDigits = digits.slice(0, digitStart) + digits.slice(digitEnd);
                    } else if (event.key === 'Backspace' && digitStart > 0) {
                        nextDigits = digits.slice(0, digitStart - 1) + digits.slice(digitStart);
                        nextDigitPosition = digitStart - 1;
                    } else if (event.key === 'Delete' && digitStart < digits.length) {
                        nextDigits = digits.slice(0, digitStart) + digits.slice(digitStart + 1);
                    } else {
                        return;
                    }

                    event.preventDefault();
                    input.value = nextDigits;
                    syncRevisionInput(input, nextDigitPosition);
                });

                input.addEventListener('input', () => {
                    syncRevisionInput(input);
                });

                input.addEventListener('blur', () => {
                    input.value = formatRevision(input.value);
                    setRevisionValidity(input);
                });
            });

            const relationRoot = document.querySelector('[data-imported-existing-relations]');

            if (!relationRoot) {
                return;
            }

            const relationList = relationRoot.querySelector('[data-imported-existing-relation-list]');
            const relationTemplate = document.querySelector('[data-imported-existing-relation-template]');
            let nextRelationIndex = relationList?.querySelectorAll('[data-imported-existing-relation-row]').length || 0;

            const syncRelationTargetVisibility = (row) => {
                const type = row.querySelector('[data-imported-existing-target-type]')?.value || 'imported';
                const importedTarget = row.querySelector('[data-imported-existing-imported-target]');
                const existingTarget = row.querySelector('[data-imported-existing-existing-target]');
                const importedSelect = importedTarget?.querySelector('select');
                const existingSelect = existingTarget?.querySelector('select');

                importedTarget?.classList.toggle('hidden', type !== 'imported');
                existingTarget?.classList.toggle('hidden', type !== 'existing');

                if (importedSelect) {
                    importedSelect.disabled = type !== 'imported';
                }

                if (existingSelect) {
                    existingSelect.disabled = type !== 'existing';
                }
            };

            const syncAllRelationRows = () => {
                relationList?.querySelectorAll('[data-imported-existing-relation-row]').forEach(syncRelationTargetVisibility);
            };

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-imported-existing-relation-add]')) {
                    const content = relationTemplate?.innerHTML.replaceAll('__INDEX__', String(nextRelationIndex));

                    if (!content || !relationList) {
                        return;
                    }

                    relationList.insertAdjacentHTML('beforeend', content);
                    nextRelationIndex += 1;
                    syncAllRelationRows();
                    return;
                }

                const removeButton = event.target.closest('[data-imported-existing-relation-remove]');

                if (removeButton) {
                    removeButton.closest('[data-imported-existing-relation-row]')?.remove();
                }
            });

            document.addEventListener('change', (event) => {
                const targetType = event.target.closest('[data-imported-existing-target-type]');

                if (!targetType) {
                    return;
                }

                const row = targetType.closest('[data-imported-existing-relation-row]');

                if (row) {
                    syncRelationTargetVisibility(row);
                }
            });

            syncAllRelationRows();
        })();
    </script>
</x-layouts.app>
