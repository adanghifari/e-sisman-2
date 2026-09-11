@props([
    'documentLevels',
    'templates' => collect(),
    'canEdit' => false,
    'canDelete' => false,
    'uploadLimits' => [],
    'activeLevel' => null,
])

@php
    $maxFiles = $uploadLimits['max_files'] ?? 10;
    $maxFileSizeKb = $uploadLimits['max_file_size_kb'] ?? 10240;
    $maxFileSizeMb = (int) ceil($maxFileSizeKb / 1024);
    $allowedExtensions = $uploadLimits['allowed_extensions'] ?? ['doc', 'docx'];
    $allowedExtensionText = strtoupper(implode(', ', $allowedExtensions));
    $templatePayload = collect($templates)->mapWithKeys(fn ($template, $levelKey) => [
        $levelKey => [
            'title' => $template->title,
            'notes' => $template->notes,
            'files' => $template->files->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->original_file_name,
                'size_kb' => (int) ceil(($file->file_size ?? 0) / 1024),
                'url' => route('document-templates.files.show', $file),
            ])->values(),
        ],
    ]);
@endphp

<div
    class="space-y-6"
    data-template-builder
    data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
    data-can-delete="{{ $canDelete ? 'true' : 'false' }}"
    data-active-level="{{ $activeLevel }}"
    data-templates='@json($templatePayload)'
>
    <template data-template-trash-icon>
        <x-flux.icon name="trash" class="size-4" />
    </template>

    <x-ui.page-header
        title="Template Dokumen"
    />

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
        <x-ui.panel title="Pilih Level Dokumen" class="h-fit xl:sticky xl:top-8">
            <div class="mt-4 space-y-3">
                @foreach ($documentLevels as $levelKey => $level)
                    <button
                        type="button"
                        class="group w-full rounded-lg border border-slate-200 bg-white px-5 py-4 text-left transition hover:border-sky-200 hover:bg-sky-50 data-[active=true]:border-sky-300 data-[active=true]:bg-sky-50 data-[active=true]:shadow-sm"
                        data-template-level-option
                        data-level-key="{{ $levelKey }}"
                        data-level-name="{{ $level['name'] }}"
                    >
                        <span class="block font-semibold text-slate-900 group-data-[active=true]:text-sky-800">
                            {{ $level['name'] }}
                        </span>
                    </button>
                @endforeach
            </div>
        </x-ui.panel>

        <div class="space-y-5">
            <x-ui.empty-state
                title="Pilih Level Dokumen"
                description="Form template akan muncul setelah level dokumen dipilih."
                data-template-empty-state
            />

            <x-ui.panel class="hidden" data-template-form-panel>
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Template Aktif</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-950" data-template-level-title></h2>
                        <p class="mt-2 hidden max-w-2xl text-sm leading-6 text-slate-500" data-template-mode-text></p>
                    </div>

                    @if ($canEdit || $canDelete)
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            @if ($canDelete)
                                <button
                                    type="button"
                                    class="hidden h-10 items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                                    data-template-delete-all
                                >
                                    <x-flux.icon name="trash" class="size-4" />
                                    Delete All
                                </button>
                            @endif

                            <x-ui.action-button type="button" variant="secondary" data-template-edit-toggle>
                                Edit Template
                            </x-ui.action-button>
                        </div>
                    @endif
                </div>

                <div class="mt-5" data-template-read-panel>
                    <div class="mb-4 hidden rounded-lg border border-slate-200 bg-white p-4" data-template-summary>
                        <h3 class="text-base font-bold text-slate-900" data-template-summary-title></h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-500" data-template-summary-notes></p>
                    </div>

                    <div class="hidden rounded-lg border border-slate-200 bg-slate-50 p-3" data-template-file-preview>
                        <div class="flex flex-col gap-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 items-center gap-3 rounded-md text-left outline-none transition hover:bg-slate-100 focus:ring-2 focus:ring-sky-100"
                                    data-template-file-summary
                                    aria-expanded="false"
                                >
                                    <span
                                        class="hidden size-9 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition"
                                        data-template-files-toggle
                                        title="Tampilkan file template"
                                    >
                                        <x-flux.icon name="chevron-down" class="size-5 transition" data-template-files-toggle-icon />
                                    </span>

                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                                        <x-flux.icon name="document-text" class="size-5" />
                                    </div>

                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-slate-900" data-template-file-name></p>
                                        <p class="mt-1 text-xs text-slate-500" data-template-file-meta></p>
                                    </div>
                                </button>

                                <div class="flex shrink-0 flex-wrap justify-end gap-2" data-template-file-links></div>
                            </div>
                            <div class="hidden space-y-2 border-t border-slate-200 pt-3" data-template-file-list></div>
                        </div>
                    </div>

                    <x-ui.empty-state
                        title="Belum Ada File Template"
                        description="Template untuk level dokumen ini belum tersedia."
                        data-template-no-file
                    />
                </div>

                <form method="POST" action="{{ route('document-templates.store') }}" enctype="multipart/form-data" class="mt-5 hidden space-y-5" data-template-edit-form>
                    @csrf
                    <input type="hidden" name="document_level" data-template-level-input>
                    <input type="hidden" name="retained_template_file_ids_present" value="1">

                    <x-ui.input
                        label="Judul Template"
                        name="title"
                        placeholder="Contoh: Template Instruksi Kerja"
                        data-template-title
                        readonly
                    />

                    @error('title')
                        <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                    @enderror

                    <x-ui.file-upload
                        label="File Template"
                        name="template_files[]"
                        accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        hint="Format: {{ $allowedExtensionText }}. Maksimal {{ $maxFiles }} file, ukuran maksimal {{ $maxFileSizeMb }} MB per file."
                        :multiple="true"
                        :max-files="$maxFiles"
                        :max-file-size-kb="$maxFileSizeKb"
                        disabled
                        data-template-file
                    />

                    <div class="mt-3 hidden rounded-lg border border-slate-200 bg-white p-4" data-template-retained-files></div>

                    @error('template_files')
                        <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                    @enderror

                    @error('retained_template_file_ids')
                        <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                    @enderror

                    @error('template_files.*')
                        <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                    @enderror

                    <x-ui.textarea
                        label="Catatan Singkat (Opsional)"
                        name="notes"
                        rows="3"
                        placeholder="Tambahkan catatan singkat jika ada."
                        data-template-notes
                        readonly
                    />

                    @error('notes')
                        <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                    @enderror

                    <div class="hidden justify-end gap-2 border-t border-slate-100 pt-5" data-template-actions>
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-600 transition hover:bg-red-50" data-template-cancel>
                            Cancel
                        </button>
                        <x-ui.action-button type="submit" data-template-save>
                            Simpan Template
                        </x-ui.action-button>
                    </div>
                </form>
            </x-ui.panel>
        </div>
    </div>
</div>

@once
    <script>
        (() => {
            document.querySelectorAll('[data-template-builder]').forEach((builder) => {
                const emptyState = builder.querySelector('[data-template-empty-state]');
                const panel = builder.querySelector('[data-template-form-panel]');
                const levelTitle = builder.querySelector('[data-template-level-title]');
                const levelInput = builder.querySelector('[data-template-level-input]');
                const titleInput = builder.querySelector('[data-template-title]');
                const notesInput = builder.querySelector('[data-template-notes]');
                const fileInput = builder.querySelector('[data-template-file]');
                const retainedFiles = builder.querySelector('[data-template-retained-files]');
                const actions = builder.querySelector('[data-template-actions]');
                const modeText = builder.querySelector('[data-template-mode-text]');
                const editToggle = builder.querySelector('[data-template-edit-toggle]');
                const deleteAllButton = builder.querySelector('[data-template-delete-all]');
                const cancelButton = builder.querySelector('[data-template-cancel]');
                const readPanel = builder.querySelector('[data-template-read-panel]');
                const editForm = builder.querySelector('[data-template-edit-form]');
                const filePreview = builder.querySelector('[data-template-file-preview]');
                const noFile = builder.querySelector('[data-template-no-file]');
                const fileName = builder.querySelector('[data-template-file-name]');
                const fileMeta = builder.querySelector('[data-template-file-meta]');
                const fileLinks = builder.querySelector('[data-template-file-links]');
                const fileList = builder.querySelector('[data-template-file-list]');
                const fileToggle = builder.querySelector('[data-template-files-toggle]');
                const fileSummary = builder.querySelector('[data-template-file-summary]');
                const fileToggleIcon = builder.querySelector('[data-template-files-toggle-icon]');
                const summary = builder.querySelector('[data-template-summary]');
                const summaryTitle = builder.querySelector('[data-template-summary-title]');
                const summaryNotes = builder.querySelector('[data-template-summary-notes]');
                const trashIconTemplate = builder.querySelector('[data-template-trash-icon]');
                const templatesByLevel = JSON.parse(builder.dataset.templates || '{}');
                const canEdit = builder.dataset.canEdit === 'true';
                const canDelete = builder.dataset.canDelete === 'true';
                let activeLevelKey = null;
                let isEditing = false;
                let retainedTemplateFiles = [];

                const selectedFiles = () => Array.from(fileInput.files || []);
                const hasActiveTemplateFiles = () => retainedTemplateFiles.length > 0 || selectedFiles().length > 0;
                const hasTemplateContent = () => {
                    const template = activeLevelKey ? (templatesByLevel[activeLevelKey] || {}) : {};

                    return Boolean(template.title || template.notes || (template.files || []).length > 0 || hasActiveTemplateFiles());
                };
                const clearSelectedFiles = () => {
                    fileInput.value = '';
                    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                };

                const syncTitleRequirement = () => {
                    titleInput.toggleAttribute('required', hasActiveTemplateFiles());
                };

                const syncLevelButtons = () => {
                    builder.querySelectorAll('[data-template-level-option]').forEach((item) => {
                        const isActive = item.dataset.levelKey === activeLevelKey;

                        item.disabled = isEditing && ! isActive;
                        item.classList.toggle('cursor-not-allowed', item.disabled);
                        item.classList.toggle('opacity-60', item.disabled);
                        item.title = item.disabled ? 'Selesaikan atau batalkan edit sebelum pindah level.' : '';
                    });
                };

                const formatStoredFileSize = (sizeKb) => {
                    const numericSize = Number(sizeKb || 0);

                    return numericSize >= 1024
                        ? `${(numericSize / 1024).toFixed(1)} MB`
                        : `${numericSize} KB`;
                };

                const renderRetainedFiles = () => {
                    if (! retainedFiles) {
                        return;
                    }

                    retainedFiles.innerHTML = '';
                    retainedFiles.className = 'mt-3 hidden rounded-lg border border-slate-200 bg-white p-4';
                    retainedFiles.classList.toggle('hidden', ! isEditing || retainedTemplateFiles.length === 0);

                    if (! isEditing || retainedTemplateFiles.length === 0) {
                        return;
                    }

                    retainedFiles.classList.add('grid', 'grid-cols-2', 'gap-4', 'sm:grid-cols-3', 'lg:grid-cols-5');

                    retainedTemplateFiles.forEach((file) => {
                        const item = document.createElement('div');
                        item.className = 'group relative min-w-0 rounded-lg border border-slate-200 bg-white p-3 text-center transition hover:border-sky-200 hover:shadow-sm';

                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'retained_template_file_ids[]';
                        hidden.value = file.id;

                        const icon = document.createElement('div');
                        icon.className = 'relative mx-auto flex h-20 w-16 items-center justify-center rounded-md border border-sky-100 bg-sky-50 text-sm font-bold text-sky-700 shadow-sm';
                        icon.textContent = 'DOC';

                        const name = document.createElement('span');
                        name.className = 'mt-3 block truncate text-sm font-semibold text-red-700';
                        name.textContent = file.name;
                        name.title = file.name;

                        const meta = document.createElement('span');
                        meta.className = 'mt-1 block text-xs text-slate-500';
                        meta.textContent = formatStoredFileSize(file.size_kb);

                        item.append(hidden, icon, name, meta);

                        if (canDelete) {
                            const removeButton = document.createElement('button');
                            removeButton.type = 'button';
                            removeButton.className = 'absolute right-2 top-2 inline-flex size-7 items-center justify-center rounded-full border border-red-200 bg-white text-sm font-bold text-red-600 opacity-0 shadow-sm transition hover:bg-red-50 group-hover:opacity-100 focus:opacity-100';
                            removeButton.setAttribute('aria-label', `Hapus ${file.name}`);
                            removeButton.title = `Hapus ${file.name}`;
                            removeButton.append(trashIconTemplate.content.cloneNode(true));
                            removeButton.addEventListener('click', () => {
                                retainedTemplateFiles = retainedTemplateFiles.filter((retainedFile) => retainedFile.id !== file.id);
                                renderRetainedFiles();
                                syncTitleRequirement();
                            });

                            item.append(removeButton);
                        }

                        retainedFiles.append(item);
                    });

                    syncTitleRequirement();
                };

                const updateReadPanel = () => {
                    const files = selectedFiles();
                    const template = activeLevelKey ? (templatesByLevel[activeLevelKey] || {}) : {};
                    const storedFiles = template.files || [];
                    const hasFile = files.length > 0 || storedFiles.length > 0;
                    const hasSummary = Boolean(template.title || template.notes);

                    summary?.classList.toggle('hidden', ! hasSummary);

                    if (summaryTitle) {
                        summaryTitle.textContent = template.title || 'Template tanpa judul';
                    }

                    if (summaryNotes) {
                        summaryNotes.textContent = template.notes || 'Tidak ada catatan.';
                    }

                    filePreview.classList.toggle('hidden', ! hasFile);
                    noFile.classList.toggle('hidden', hasFile);
                    fileLinks.innerHTML = '';
                    fileList.innerHTML = '';
                    fileList.classList.add('hidden');
                    fileToggle.classList.add('hidden');
                    fileToggle.classList.remove('inline-flex');
                    fileSummary.setAttribute('aria-expanded', 'false');
                    fileSummary.disabled = true;
                    fileToggleIcon?.classList.remove('rotate-180');

                    if (files.length > 0) {
                        const totalSizeKb = files.reduce((total, file) => total + Math.ceil(file.size / 1024), 0);

                        fileName.textContent = files.length > 1
                            ? `${files.length} file template dipilih`
                            : files[0].name;
                        fileMeta.textContent = `${totalSizeKb} KB total`;
                    } else if (storedFiles.length > 0) {
                        fileName.textContent = storedFiles.length > 1
                            ? `${storedFiles.length} file template aktif`
                            : storedFiles[0].name;
                        fileMeta.textContent = `${storedFiles.reduce((total, file) => total + Number(file.size_kb || 0), 0)} KB total`;

                        if (storedFiles.length === 1) {
                            const link = document.createElement('a');
                            link.href = storedFiles[0].url;
                            link.target = '_blank';
                            link.className = 'inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50';
                            link.textContent = 'Download';
                            fileLinks.append(link);
                        } else {
                            fileToggle.classList.remove('hidden');
                            fileToggle.classList.add('inline-flex');
                            fileSummary.disabled = false;

                            storedFiles.forEach((file, index) => {
                                const row = document.createElement('div');
                                row.className = 'grid min-h-12 grid-cols-[minmax(0,1fr)_88px] items-center gap-3 rounded-md border border-slate-200 bg-white px-3 py-2';

                                const info = document.createElement('div');
                                info.className = 'min-w-0';

                                const name = document.createElement('p');
                                name.className = 'truncate text-sm font-semibold text-slate-800';
                                name.textContent = file.name || `File ${index + 1}`;

                                const meta = document.createElement('p');
                                meta.className = 'mt-0.5 text-xs font-medium text-slate-500';
                                meta.textContent = `${Number(file.size_kb || 0)} KB`;

                                const link = document.createElement('a');
                                link.href = file.url;
                                link.target = '_blank';
                                link.className = 'inline-flex h-8 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50';
                                link.textContent = 'Download';

                                info.append(name, meta);
                                row.append(info, link);
                                fileList.append(row);
                            });
                        }
                    }

                };

                const setEditing = (editing) => {
                    isEditing = (canEdit || canDelete) && editing;

                    titleInput.toggleAttribute('readonly', ! isEditing || ! canEdit);
                    notesInput.toggleAttribute('readonly', ! isEditing || ! canEdit);
                    fileInput.toggleAttribute('disabled', ! isEditing || ! canEdit);
                    actions.classList.toggle('hidden', ! isEditing);
                    actions.classList.toggle('flex', isEditing);
                    readPanel.classList.toggle('hidden', isEditing);
                    editForm.classList.toggle('hidden', ! isEditing);

                    modeText.textContent = isEditing
                        ? 'Upload file Word. Maksimal {{ $maxFiles }} file, {{ $maxFileSizeMb }} MB per file.'
                        : '';
                    modeText.classList.toggle('hidden', ! isEditing);

                    if (editToggle) {
                        editToggle.classList.toggle('hidden', isEditing);
                        editToggle.textContent = canEdit ? 'Edit Template' : 'Kelola Template';
                    }

                    if (deleteAllButton) {
                        const showDeleteAll = canDelete && hasTemplateContent();

                        deleteAllButton.classList.toggle('hidden', ! showDeleteAll);
                        deleteAllButton.classList.toggle('inline-flex', showDeleteAll);
                    }

                    if (! isEditing) {
                        clearSelectedFiles();
                        renderTemplate(templatesByLevel[activeLevelKey] || {});
                        updateReadPanel();
                    }

                    renderRetainedFiles();
                    syncTitleRequirement();
                    syncLevelButtons();
                };

                const persistActiveTemplate = () => {};

                const renderTemplate = (template = {}) => {
                    titleInput.value = template.title || '';
                    notesInput.value = template.notes || '';
                    retainedTemplateFiles = [...(template.files || [])];
                    renderRetainedFiles();
                    updateReadPanel();
                };

                const selectLevel = (option) => {
                    if (isEditing && option.dataset.levelKey !== activeLevelKey) {
                        return;
                    }

                    clearSelectedFiles();
                    activeLevelKey = option.dataset.levelKey;
                    levelInput.value = activeLevelKey;

                    builder.querySelectorAll('[data-template-level-option]').forEach((item) => {
                        item.dataset.active = String(item === option);
                    });

                    levelTitle.textContent = option.dataset.levelName;
                    renderTemplate(templatesByLevel[activeLevelKey] || {});
                    setEditing(false);

                    emptyState.classList.add('hidden');
                    panel.classList.remove('hidden');
                    syncLevelButtons();
                };

                builder.querySelectorAll('[data-template-level-option]').forEach((option) => {
                    option.addEventListener('click', () => selectLevel(option));
                });

                fileSummary?.addEventListener('click', () => {
                    if (fileSummary.disabled) {
                        return;
                    }

                    const expanded = fileSummary.getAttribute('aria-expanded') === 'true';

                    fileSummary.setAttribute('aria-expanded', String(! expanded));
                    fileList.classList.toggle('hidden', expanded);
                    fileToggleIcon?.classList.toggle('rotate-180', ! expanded);
                });

                if (editToggle) {
                    editToggle.addEventListener('click', () => setEditing(true));
                }

                deleteAllButton?.addEventListener('click', () => {
                    if (! window.confirm('Hapus semua file template untuk level dokumen ini?')) {
                        return;
                    }

                    retainedTemplateFiles = [];
                    titleInput.value = '';
                    notesInput.value = '';
                    clearSelectedFiles();
                    renderRetainedFiles();
                    syncTitleRequirement();

                    if (! isEditing) {
                        editForm.requestSubmit();
                    }
                });

                cancelButton?.addEventListener('click', () => setEditing(false));

                panel.addEventListener('input', persistActiveTemplate);
                panel.addEventListener('change', () => {
                    persistActiveTemplate();
                    updateReadPanel();
                    syncTitleRequirement();
                });

                setEditing(false);

                const initialLevel = builder.dataset.activeLevel;
                const initialOption = initialLevel
                    ? builder.querySelector(`[data-template-level-option][data-level-key="${initialLevel}"]`)
                    : builder.querySelector('[data-template-level-option]');

                if (initialOption) {
                    selectLevel(initialOption);
                }
            });
        })();
    </script>
@endonce
