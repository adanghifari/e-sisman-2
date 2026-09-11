@php
    $isMasterImport = ($documentState ?? \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::STATE_OBSOLETE) === \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::STATE_MASTER;
    $legacyOnly = $legacyOnly ?? false;
    $pageTitle = $isMasterImport
        ? 'Import Dokumen Master'
        : ($legacyOnly ? 'Import Dokumen Obsolete Legacy' : 'Tambah Arsip Dokumen Existing');
    $pageDescription = $isMasterImport
        ? 'Upload manual dokumen master existing sebelum go-live tanpa masuk approval awal.'
        : ($legacyOnly ? 'Upload dokumen obsolete yang masih mengikuti ketentuan dokumen lama.' : 'Upload manual dokumen obsolete tanpa mengubah lifecycle dokumen master.');
@endphp

<x-layouts.app :title="__($pageTitle)">
    <div class="space-y-6">
        <x-ui.page-header
            :title="$pageTitle"
            :description="$pageDescription"
        />

        <form
            method="POST"
            action="{{ $formAction }}"
            enctype="multipart/form-data"
            class="space-y-6"
            @if ($isMasterImport)
                data-import-master-number-check-url="{{ route('documents.existing.imports.number-reuse-check') }}"
            @endif
        >
            @csrf
            <input type="hidden" name="document_state" value="{{ $documentState ?? \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::STATE_OBSOLETE }}">
            <input type="hidden" name="confirm_imported_master_number_reuse" value="0">
            @if ($isMasterImport)
                <input type="hidden" name="obsolete_rule_type" value="{{ \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::CURRENT_RULE }}">
            @elseif ($legacyOnly)
                <input type="hidden" name="obsolete_rule_type" value="{{ \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::LEGACY_RULE }}">
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    Mohon periksa kembali data arsip. Ada isian yang belum sesuai.
                </div>
            @endif

            <x-ui.panel title="Identitas Dokumen" description="{{ $legacyOnly ? 'Isi identitas arsip sesuai dokumen lama.' : 'Mulai dari nama dokumen, lalu pilih ketentuan arsip yang sesuai.' }}">
                @php
                    $selectedRuleType = old(
                        'obsolete_rule_type',
                        $isMasterImport
                            ? \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::CURRENT_RULE
                            : ($legacyOnly ? \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::LEGACY_RULE : null),
                    );
                @endphp

                <div class="space-y-5" data-imported-existing-rule-form>
                    <div>
                        <x-ui.input label="Nama Dokumen" name="nama_dokumen" :value="old('nama_dokumen')" required />
                        @error('nama_dokumen')
                            <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    @unless ($isMasterImport || $legacyOnly)
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Ketentuan</p>
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="relative cursor-pointer rounded-lg border border-slate-200 bg-white p-4 transition has-[:checked]:border-sky-400 has-[:checked]:bg-sky-50 has-[:checked]:ring-2 has-[:checked]:ring-sky-100">
                                    <input
                                        type="radio"
                                        name="obsolete_rule_type"
                                        value="{{ \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::CURRENT_RULE }}"
                                        class="sr-only"
                                        data-imported-existing-rule-option
                                        @checked($selectedRuleType === \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::CURRENT_RULE)
                                    >
                                    <span class="flex items-start gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700">
                                            <x-flux.icon name="check-badge" class="size-5" />
                                        </span>
                                        <span>
                                            <span class="block font-semibold text-slate-900">Sesuai Ketentuan Saat Ini</span>
                                            <span class="mt-1 block text-sm leading-5 text-slate-500">Gunakan pemetaan master data modern seperti dok level, jenis dokumen, proses, dan fungsi.</span>
                                        </span>
                                    </span>
                                </label>

                                <label class="relative cursor-pointer rounded-lg border border-slate-200 bg-white p-4 transition has-[:checked]:border-sky-400 has-[:checked]:bg-sky-50 has-[:checked]:ring-2 has-[:checked]:ring-sky-100">
                                    <input
                                        type="radio"
                                        name="obsolete_rule_type"
                                        value="{{ \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::LEGACY_RULE }}"
                                        class="sr-only"
                                        data-imported-existing-rule-option
                                        @checked($selectedRuleType === \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::LEGACY_RULE)
                                    >
                                    <span class="flex items-start gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-700">
                                            <x-flux.icon name="archive-box" class="size-5" />
                                        </span>
                                        <span>
                                            <span class="block font-semibold text-slate-900">Mengikuti Ketentuan Dokumen Lama</span>
                                            <span class="mt-1 block text-sm leading-5 text-slate-500">Simpan identitas historis dokumen sebagaimana tertulis pada arsip lama.</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            @error('obsolete_rule_type')
                                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                        </div>
                    @endunless

                    <div class="grid gap-4 md:grid-cols-2" data-rule-dependent-fields>
                        <x-ui.input label="Nomor Dokumen" name="nomor_dokumen" :value="old('nomor_dokumen')" />
                        <x-ui.input
                            label="Nomor Revisi"
                            name="nomor_revisi"
                            :value="old('nomor_revisi')"
                            :placeholder="$isMasterImport ? 'Contoh: 00.00' : 'Contoh: 00.00, Rev A, R02'"
                        />
                        <x-ui.date-input label="Tanggal Terbit" name="tanggal_terbit" :value="old('tanggal_terbit')" />
                        @unless ($isMasterImport)
                            <x-ui.date-input label="Tanggal Obsolete" name="tanggal_obsolete" :value="old('tanggal_obsolete')" />
                        @endunless
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-current-rule-fields @if ($isMasterImport) data-always-visible @endif>
                        <div class="mb-4 flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-sky-700 ring-1 ring-sky-100">
                                <x-flux.icon name="squares-2x2" class="size-5" />
                            </span>
                            <div>
                                <h3 class="font-semibold text-slate-900">Pemetaan Ketentuan Saat Ini</h3>
                                <p class="mt-1 text-sm leading-5 text-slate-500">{{ $isMasterImport ? 'Field ini wajib untuk imported master agar tampil seragam di Dokumen Master.' : 'Isi jika dokumen obsolete ini masih dapat dipetakan ke struktur dokumen modern.' }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.select label="Dok Level" name="m_document_level_id" :value="old('m_document_level_id')" :options="$documentLevelOptions" />
                            @error('m_document_level_id')
                                <span class="text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                            <x-ui.select label="Jenis Dokumen" name="m_document_types_id" :value="old('m_document_types_id')" :options="$documentTypeOptions" />
                            @error('m_document_types_id')
                                <span class="text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                            <x-ui.select label="Proses Bisnis" name="m_proses_bisnis_id" :value="old('m_proses_bisnis_id')" :options="$processOptions" />
                            @error('m_proses_bisnis_id')
                                <span class="text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                            <x-ui.select label="Proses Fungsi" name="m_proses_fungsi_id" :value="old('m_proses_fungsi_id')" :options="$functionOptions" />
                            @error('m_proses_fungsi_id')
                                <span class="text-sm font-semibold text-red-500">{{ $message }}</span>
                            @enderror
                            @unless ($isMasterImport)
                                <div class="md:col-span-2">
                                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Digantikan Oleh</span>
                                    <x-ui.document-search-select
                                        name="replacement_reference"
                                        :documents="$relationDocumentOptions"
                                        :value="old('replacement_reference')"
                                        placeholder="Belum ditentukan"
                                        empty-label="Dokumen tidak ditemukan."
                                        :filter-by-context="! $legacyOnly"
                                    />
                                    @error('replacement_reference')
                                        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endunless
                        </div>
                    </div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800" data-legacy-rule-note>
                        <p class="font-semibold">Metadata historis dokumen lama belum dipaksa ke struktur master data saat ini.</p>
                        <p class="mt-1">Untuk tahap ini, simpan nomor dokumen, revisi, tanggal, file, catatan, dan relasi dokumen. Field legacy tambahan akan ditentukan setelah audit format arsip aktual.</p>
                    </div>

                    <div data-rule-dependent-fields>
                        <x-ui.textarea label="Catatan Dokumen" name="catatan" :value="old('catatan')" :placeholder="$isMasterImport ? 'Catatan bebas terkait imported master ini.' : 'Catatan bebas terkait arsip obsolete ini.'" />
                    </div>
                </div>
            </x-ui.panel>

            <x-ui.panel title="File Dokumen" :description="$isMasterImport ? 'Upload file utama dokumen master existing dan lampiran pendukung jika ada.' : 'Upload file utama dokumen obsolete dan lampiran pendukung jika ada.'" data-rule-dependent-section>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.file-upload label="File Dokumen" :name="$isMasterImport ? 'existing_document' : 'obsolete_document'" accept=".pdf,.doc,.docx,.xls,.xlsx" required />
                    <x-ui.file-upload label="Lampiran" name="attachments[]" accept=".pdf,.doc,.docx,.xls,.xlsx" multiple />
                </div>
                @error($isMasterImport ? 'existing_document' : 'obsolete_document')
                    <span class="mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                @enderror
                @error('attachments')
                    <span class="mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                @enderror
            </x-ui.panel>

            <x-ui.panel
                title="Dokumen Terkait"
                :description="$isMasterImport ? 'Tambahkan relasi dokumen bila diperlukan, maksimal dua relasi.' : 'Tambahkan Dokumen Pengganti dan Referensi Prosedur bila diperlukan, maksimal dua relasi.'"
                :padded="false"
                data-legacy-rule-section
            >
                @php
                    $oldRelations = collect(old('relations', []))->values();
                @endphp

                <div class="space-y-4 px-5 py-5" data-imported-existing-relations>
                    @error('relations.0.related_imported_existing_document_id')
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $message }}</div>
                    @enderror

                    <div class="space-y-4" data-imported-existing-relation-list>
                        @foreach ($oldRelations as $index => $relation)
                            @php
                                $relationReference = $relation['relation_reference'] ?? (
                                    filled($relation['related_document_id'] ?? null)
                                        ? 'existing-'.$relation['related_document_id']
                                        : (filled($relation['related_imported_existing_document_id'] ?? null)
                                            ? 'imported-'.$relation['related_imported_existing_document_id']
                                            : null)
                                );
                            @endphp

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-imported-existing-relation-row>
                                <div class="mb-4 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">Relasi Dokumen</p>
                                        <p class="mt-1 text-xs font-medium text-slate-500">Pilih tepat satu target relasi.</p>
                                    </div>
                                    <button type="button" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" data-imported-existing-relation-remove aria-label="Hapus relasi">
                                        <x-flux.icon name="x-mark" class="size-4" />
                                    </button>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <x-ui.select
                                        label="Jenis Relasi"
                                        name="relations[{{ $index }}][relation_type]"
                                        :value="$relation['relation_type'] ?? \App\Models\DocumentRelation::SUPERSEDED_BY"
                                        :options="$relationTypeOptions"
                                        data-imported-existing-relation-type
                                    />
                                    <div>
                                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Target Dokumen</span>
                                        <x-ui.document-search-select
                                            name="relations[{{ $index }}][relation_reference]"
                                            :documents="$relationDocumentOptions"
                                            :value="$relationReference"
                                            placeholder="Pilih target dokumen"
                                            empty-label="Dokumen tidak ditemukan."
                                            data-relation-target-picker
                                        />
                                    </div>
                                    <div class="lg:col-span-2">
                                        <x-ui.textarea
                                            label="Keterangan Relasi"
                                            name="relations[{{ $index }}][keterangan]"
                                            :value="$relation['keterangan'] ?? null"
                                            rows="3"
                                            placeholder="Keterangan khusus untuk hubungan antar dokumen."
                                        />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-imported-existing-relation-add>
                        <x-flux.icon name="plus" class="size-4" />
                        Tambah Relasi
                    </button>
                    <p class="hidden text-xs font-semibold text-slate-500" data-imported-existing-relation-limit>
                        Maksimal dua relasi: Digantikan Oleh dan Referensi.
                    </p>
                </div>

                <template data-imported-existing-relation-template>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-imported-existing-relation-row>
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Relasi Dokumen</p>
                                <p class="mt-1 text-xs font-medium text-slate-500">Pilih tepat satu target relasi.</p>
                            </div>
                            <button type="button" class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" data-imported-existing-relation-remove aria-label="Hapus relasi">
                                <x-flux.icon name="x-mark" class="size-4" />
                            </button>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Relasi</span>
                                <select name="relations[__INDEX__][relation_type]" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100" data-imported-existing-relation-type>
                                    @foreach ($relationTypeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div>
                                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Target Dokumen</span>
                                <x-ui.document-search-select
                                    name="relations[__INDEX__][relation_reference]"
                                    :documents="$relationDocumentOptions"
                                    placeholder="Pilih target dokumen"
                                    empty-label="Dokumen tidak ditemukan."
                                    data-relation-target-picker
                                />
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block">
                                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan Relasi</span>
                                    <textarea name="relations[__INDEX__][keterangan]" rows="3" placeholder="Keterangan khusus untuk hubungan antar dokumen." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"></textarea>
                                </label>
                            </div>
                        </div>
                    </div>
                </template>
            </x-ui.panel>

            <div class="flex justify-end gap-3">
                <a href="{{ $cancelUrl }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Batal
                </a>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    {{ $isMasterImport ? 'Simpan Dokumen Master' : 'Simpan Arsip' }}
                </button>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const ruleForm = document.querySelector('[data-imported-existing-rule-form]');

            if (ruleForm) {
                const syncRuleFields = () => {
                    const selectedRule = ruleForm.querySelector('[data-imported-existing-rule-option]:checked')?.value
                        || document.querySelector('input[type="hidden"][name="obsolete_rule_type"]')?.value;
                    const currentRuleFields = ruleForm.querySelector('[data-current-rule-fields]');
                    const legacyRuleNote = ruleForm.querySelector('[data-legacy-rule-note]');
                    const dependentFields = document.querySelectorAll('[data-rule-dependent-fields]');
                    const dependentSections = document.querySelectorAll('[data-rule-dependent-section]');
                    const legacySections = document.querySelectorAll('[data-legacy-rule-section]');
                    const currentRuleAlwaysVisible = currentRuleFields?.hasAttribute('data-always-visible') || false;
                    const hasSelectedRule = Boolean(selectedRule) || currentRuleAlwaysVisible;
                    const isCurrentRule = selectedRule === '{{ \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::CURRENT_RULE }}';
                    const isLegacyRule = selectedRule === '{{ \App\Http\Controllers\DocumentManagement\ImportedExistingDocumentController::LEGACY_RULE }}';
                    const shouldShowCurrentRule = currentRuleAlwaysVisible || isCurrentRule;

                    dependentFields.forEach((section) => {
                        section.classList.toggle('hidden', !hasSelectedRule);
                    });
                    dependentSections.forEach((section) => {
                        section.classList.toggle('hidden', !hasSelectedRule);
                    });
                    legacySections.forEach((section) => {
                        const hideLegacySection = !hasSelectedRule || !isLegacyRule;

                        section.classList.toggle('hidden', hideLegacySection);
                        section.querySelectorAll('select, input, textarea').forEach((field) => {
                            field.disabled = hideLegacySection;
                        });
                    });

                    currentRuleFields?.classList.toggle('hidden', !hasSelectedRule || !shouldShowCurrentRule);
                    legacyRuleNote?.classList.toggle('hidden', !hasSelectedRule || shouldShowCurrentRule);

                    currentRuleFields?.querySelectorAll('select, input, textarea').forEach((field) => {
                        field.disabled = !hasSelectedRule || !shouldShowCurrentRule;
                    });
                };

                document.addEventListener('change', (event) => {
                    if (event.target.closest('[data-imported-existing-rule-option]')) {
                        syncRuleFields();
                    }
                });

                syncRuleFields();
            }

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

                if (window.confirm(payload.message || 'Nomor dokumen sudah digunakan. Apakah Anda yakin ingin melanjutkan?')) {
                    if (confirmationInput) {
                        confirmationInput.value = '1';
                    }

                    return true;
                }

                return false;
            };

            document.querySelectorAll('form').forEach((form) => {
                syncDocumentSearchOptions(form);

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
                if (!event.target.closest('[name="m_document_level_id"], select[name="m_proses_bisnis_id"], select[name="m_proses_fungsi_id"], [data-imported-existing-relation-type]')) {
                    return;
                }

                const form = event.target.closest('form');
                if (form) {
                    syncDocumentSearchOptions(form);
                }
            });

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

            const root = document.querySelector('[data-imported-existing-relations]');

            if (!root) {
                return;
            }

            const list = root.querySelector('[data-imported-existing-relation-list]');
            const template = document.querySelector('[data-imported-existing-relation-template]');
            const addButton = root.querySelector('[data-imported-existing-relation-add]');
            const limitMessage = root.querySelector('[data-imported-existing-relation-limit]');
            const maxRelations = 2;
            let nextIndex = list?.querySelectorAll('[data-imported-existing-relation-row]').length || 0;

            const syncAddButton = () => {
                const rowCount = list?.querySelectorAll('[data-imported-existing-relation-row]').length || 0;
                const isLimitReached = rowCount >= maxRelations;

                addButton?.classList.toggle('hidden', isLimitReached);
                limitMessage?.classList.toggle('hidden', !isLimitReached);
            };

            const syncTargetVisibility = (row) => {
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

            const syncAllRows = () => {
                list?.querySelectorAll('[data-imported-existing-relation-row]').forEach(syncTargetVisibility);
                syncAddButton();
            };

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-imported-existing-relation-add]')) {
                    const rowCount = list?.querySelectorAll('[data-imported-existing-relation-row]').length || 0;

                    if (rowCount >= maxRelations) {
                        syncAddButton();

                        return;
                    }

                    const content = template?.innerHTML.replaceAll('__INDEX__', String(nextIndex));

                    if (!content || !list) {
                        return;
                    }

                    list.insertAdjacentHTML('beforeend', content);
                    nextIndex += 1;
                    syncDocumentSearchOptions(event.target.closest('form'));
                    syncAllRows();
                    return;
                }

                const removeButton = event.target.closest('[data-imported-existing-relation-remove]');

                if (removeButton) {
                    removeButton.closest('[data-imported-existing-relation-row]')?.remove();
                    syncAddButton();
                }
            });

            document.addEventListener('change', (event) => {
                const targetType = event.target.closest('[data-imported-existing-target-type]');

                if (!targetType) {
                    return;
                }

                const row = targetType.closest('[data-imported-existing-relation-row]');

                if (row) {
                    syncTargetVisibility(row);
                }
            });

            syncAllRows();
        })();
    </script>
</x-layouts.app>

