@php
    $levelNumbers = [
        'level-1' => 'I',
        'level-2' => 'II',
        'level-3' => 'III',
    ];
    $documentTitle = \Illuminate\Support\Str::after($levelConfig['name'], ': ');
    $isImportedMaster = $document->isMaster();
    $isLegacyImport = $document->origin === \App\Models\Document::ORIGIN_IMPORTED_LEGACY;
    $pageTitle = 'Edit Metadata Dokumen Imported Level '.$levelNumbers[$level].' : '.$documentTitle;
    $detailRoute = $isImportedMaster
        ? route('documents.master.imported.show', $document)
        : route('documents.existing.imports.show', $document);
    $updateRoute = route('documents.master.imports.update', $document);

    $documentPrefixes = [
        'level-1' => 'SM',
        'level-2' => 'PS',
        'level-3' => 'IK',
    ];
    $documentNumberPrefix = $documentPrefixes[$level] ?? 'DOC';
    $selectedBusinessProcessId = old('m_proses_bisnis_id', $document->m_proses_bisnis_id);
    $selectedBusinessFunctionId = old('m_proses_fungsi_id', $document->m_proses_fungsi_id);
    $selectedBusinessFunction = isset($businessFunctions)
        ? $businessFunctions->firstWhere('id', (int) $selectedBusinessFunctionId)
        : null;
    $documentNumberFunctionCode = $selectedBusinessFunction?->kode ?: 'XXX';
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
            <a href="{{ route('documents.master') }}" class="transition hover:text-sky-700">Dokumen Master</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ $detailRoute }}" class="transition hover:text-sky-700">{{ $document->nomor_dokumen ?: $document->nama_dokumen }}</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">Edit Metadata</span>
        </nav>

        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                    Edit Metadata Dokumen Level {{ $levelNumbers[$level] }} : {{ $documentTitle }}
                </h1>
                <p class="mt-1 text-sm font-medium text-slate-500">
                    Ubah metadata dokumen imported yang sudah masuk sistem.
                </p>
            </div>
            <span class="inline-flex h-8 items-center rounded-full bg-amber-50 px-3 text-xs font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                Akses Khusus Admin
            </span>
        </div>

        <form
            method="POST"
            action="{{ $updateRoute }}"
            enctype="multipart/form-data"
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="m_document_level_id" value="{{ $documentLevelRecord?->id }}">

            <div class="space-y-6">
                @if ($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                        <p>Perubahan metadata belum berhasil disimpan. Cek isian berikut:</p>
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
                                value="{{ old('nama_dokumen', $document->nama_dokumen) }}"
                                placeholder="Masukkan nama dokumen"
                                @unless ($isLegacyImport) required @endunless
                                @class([
                                    'h-14 w-full rounded-lg bg-white px-4 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:ring-2',
                                    'border border-red-300 focus:border-red-400 focus:ring-red-100' => $errors->has('nama_dokumen'),
                                    'border border-slate-300 focus:border-sky-400 focus:ring-sky-100' => ! $errors->has('nama_dokumen'),
                                ])
                            >
                        </label>

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

                        @if ($level === 'level-1')
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Department</span>
                                <select
                                    name="department_ids[]"
                                    @unless ($isLegacyImport) required @endunless
                                    class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >
                                    <option value="">Pilih Department</option>
                                    @foreach ($departmentOptions as $dept)
                                        <option value="{{ $dept['value'] }}" @selected(in_array((string) $dept['value'], $selectedDepartmentIds, true))>
                                            {{ $dept['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('department_ids')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>
                        @else
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Proses / Fungsi</span>
                                <select
                                    name="m_proses_fungsi_id"
                                    required
                                    class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >
                                    <option value="">Pilih Proses Fungsi</option>
                                    @foreach ($businessFunctions as $function)
                                        <option
                                            value="{{ $function->id }}"
                                            data-function-code="{{ $function->kode }}"
                                            @selected((string) $selectedBusinessFunctionId === (string) $function->id)
                                        >
                                            {{ $function->nama_proses_fungsi }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('m_proses_fungsi_id')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </label>

                            <div class="block md:col-span-2">
                                <x-ui.multi-select
                                    label="Department Terkait"
                                    name="department_ids"
                                    :options="$departmentOptions"
                                    :selected="$selectedDepartmentIds"
                                    placeholder="Pilih department"
                                    selected-placeholder="Tambah Department"
                                    :required="! $isLegacyImport"
                                />
                                @error('department_ids')
                                    <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif

                        @if ($level === 'level-3')
                            <label class="block md:col-span-2">
                                <span class="mb-2 block text-base font-medium text-slate-500">Dokumen Acuan (Prosedur Level II)</span>
                                <select
                                    name="reference"
                                    @unless ($isLegacyImport) required @endunless
                                    class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-500 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >
                                    <option value="">Pilih Dokumen Acuan</option>
                                    @if ($importedProcedures->isNotEmpty())
                                        <optgroup label="Prosedur Master Existing (Imported)">
                                            @foreach ($importedProcedures as $proc)
                                                <option
                                                    value="imported-{{ $proc->id }}"
                                                    data-business-process-id="{{ $proc->m_proses_bisnis_id }}"
                                                    data-business-function-id="{{ $proc->m_proses_fungsi_id }}"
                                                    @selected((string) $selectedReference === 'imported-'.$proc->id)
                                                >
                                                    {{ $proc->nomor_dokumen ?: '-' }} - {{ $proc->nama_dokumen }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if ($workflowProcedures->isNotEmpty())
                                        <optgroup label="Prosedur Master Sistem (Workflow V2)">
                                            @foreach ($workflowProcedures as $proc)
                                                <option
                                                    value="existing-{{ $proc->id }}"
                                                    data-business-process-id="{{ $proc->m_proses_bisnis_id }}"
                                                    data-business-function-id="{{ $proc->m_proses_fungsi_id }}"
                                                    @selected((string) $selectedReference === 'existing-'.$proc->id)
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
                    <div class="space-y-4 px-6 py-6" data-single-file-section>
                        @if ($currentFile)
                            {{-- Tampilan saat file sudah ada (langsung menunjukkan ada file) --}}
                            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition" data-existing-file-card>
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="flex size-12 shrink-0 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 shadow-sm">
                                        <x-flux.icon name="document-text" class="size-6" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-800" title="{{ $currentFile->original_file_name }}">
                                            {{ $currentFile->original_file_name }}
                                        </p>
                                        <p class="mt-0.5 text-xs font-medium text-slate-500">
                                            Dokumen Existing &bull; {{ number_format(($currentFile->file_size ?? 0) / 1024, 1) }} KB
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0 ml-4">
                                    <a
                                        href="{{ route('documents.existing.imports.files.preview', [$document, $currentFile]) }}"
                                        target="_blank"
                                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                                    >
                                        <x-flux.icon name="eye" class="size-3.5 text-slate-500" />
                                        Lihat Dokumen
                                    </a>

                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 shadow-sm transition hover:bg-red-50 hover:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-500/20"
                                        data-action-remove-existing
                                        title="Hapus / Ganti File"
                                        aria-label="Silang untuk ganti file"
                                    >
                                        <x-flux.icon name="x-mark" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- Dropzone untuk upload file pengganti (hanya muncul setelah di-silang atau jika belum ada file) --}}
                        <div class="{{ $currentFile ? 'hidden' : '' }}" data-new-file-container>
                            <x-ui.file-upload
                                label="Unggah File Dokumen Pengganti"
                                name="existing_document"
                                accept=".pdf,.doc,.docx,.xls,.xlsx"
                                hint="Format PDF, Word, atau Excel. Maks 10 MB (Maksimal 1 file)."
                                :max-files="1"
                                :max-file-size-kb="10240"
                            />
                            @error('existing_document')
                                <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror

                            @if ($currentFile)
                                <div class="mt-3 flex justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                                        data-action-restore-existing
                                    >
                                        <x-flux.icon name="arrow-uturn-left" class="size-3.5 text-slate-500" />
                                        Batal Ganti File
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-documents.form-section>
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
                            :default-value="$nomorDokumenSuffix"
                            label="Nomor Dokumen"
                        />

                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Revisi</span>
                            <input
                                type="text"
                                name="nomor_revisi"
                                value="{{ old('nomor_revisi', $document->nomor_revisi) }}"
                                placeholder="__.__"
                                inputmode="numeric"
                                @unless ($isLegacyImport)
                                    pattern="\d{2}\.\d{2}"
                                    title="Gunakan format 00.00"
                                    data-import-master-revision-mask
                                @else
                                    title="Nomor revisi legacy mengikuti dokumen asli"
                                @endunless
                                autocomplete="off"
                                required
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
                            :value="old('tanggal_terbit', $document->tanggal_terbit?->format('Y-m-d'))"
                        />
                        @error('tanggal_terbit')
                            <span class="-mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror

                        <div>
                            <span class="mb-2 block text-base font-medium text-slate-500">Catatan</span>
                            <textarea
                                name="catatan"
                                rows="4"
                                placeholder="Catatan bebas terkait dokumen master ini."
                                class="w-full resize-none rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            >{{ old('catatan', $document->catatan) }}</textarea>
                            @error('catatan')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
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
                                <span class="ml-auto rounded-full {{ $isImportedMaster ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }} px-3 py-1 text-xs font-bold">
                                    {{ $isImportedMaster ? 'Master' : 'Obsolete' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-slate-200 px-6 py-5">
                        <div class="flex gap-3">
                            <a
                                href="{{ $detailRoute }}"
                                class="inline-flex h-12 flex-1 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:bg-slate-50"
                            >
                                Batal
                            </a>
                            <button
                                type="submit"
                                class="inline-flex h-12 flex-1 items-center justify-center rounded-lg bg-sky-600 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-sky-700"
                            >
                                Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </section>
            </aside>
        </form>
    </div>

    <script>
        (() => {
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

            document.querySelectorAll('form').forEach((form) => {
                syncProcedureReferenceOptions(form);
                syncDocumentNumberFunctionSegment(form);
                syncProcedureReferenceNumberSegments(form);

                form.addEventListener('submit', () => {
                    const suffixInput = form.querySelector('input[name="nomor_dokumen_suffix"]');
                    if (suffixInput) {
                        const val = suffixInput.value.trim();
                        if (/^\d{1}$/.test(val)) {
                            suffixInput.value = val.padStart(2, '0');
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
                    syncDocumentNumberFunctionSegment(form);
                }

                if (event.target.closest('select[name="reference"]')) {
                    syncProcedureReferenceNumberSegments(form);
                }
            });

            document.addEventListener('click', (event) => {
                const removeBtn = event.target.closest('[data-action-remove-existing]');
                if (removeBtn) {
                    const section = removeBtn.closest('[data-single-file-section]');
                    const fileCard = section?.querySelector('[data-existing-file-card]');
                    const uploadContainer = section?.querySelector('[data-new-file-container]');

                    fileCard?.classList.add('hidden');
                    uploadContainer?.classList.remove('hidden');
                    return;
                }

                const restoreBtn = event.target.closest('[data-action-restore-existing]');
                if (restoreBtn) {
                    const section = restoreBtn.closest('[data-single-file-section]');
                    const fileCard = section?.querySelector('[data-existing-file-card]');
                    const uploadContainer = section?.querySelector('[data-new-file-container]');
                    const fileInput = section?.querySelector('input[type="file"]');

                    if (fileInput) {
                        fileInput.value = '';
                        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    uploadContainer?.classList.add('hidden');
                    fileCard?.classList.remove('hidden');
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
        })();
    </script>
</x-layouts.app>
