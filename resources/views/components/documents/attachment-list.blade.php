@props([
    'existingFiles' => collect(),
    'showCreateControls' => true,
])

@php
    $existingFiles = collect($existingFiles);
@endphp

<div class="space-y-4" data-attachment-list>
    <div class="space-y-3" data-attachment-items>
        @foreach ($existingFiles as $file)
            @php
                $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION) ?: 'file');
                $isPdf = $extension === 'pdf';
                $size = (int) ($file['size'] ?? 0);
                $sizeKb = (int) ceil($size / 1024);
                $formattedSize = $sizeKb >= 1024
                    ? number_format($sizeKb / 1024, 1).' MB'
                    : $sizeKb.' KB';
            @endphp

            <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_minmax(260px,0.9fr)_auto]" data-existing-attachment-row>
                <input type="hidden" name="existing_attachment_orders[{{ $file['id'] ?? '' }}]" value="{{ $file['order'] ?? $loop->iteration }}" data-attachment-order-input>
                <label class="block min-w-0">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Dokumen/Lampiran</span>
                    <input
                        type="text"
                        name="existing_attachment_titles[{{ $file['id'] ?? '' }}]"
                        value="{{ $file['title'] ?? '' }}"
                        placeholder="Masukkan nama lampiran"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                    >
                </label>

                <div class="min-w-0">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">File</span>
                    <div class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <span @class([
                            'grid size-9 shrink-0 place-items-center rounded-md border text-[10px] font-bold',
                            'border-red-100 bg-red-50 text-red-600' => $isPdf,
                            'border-sky-100 bg-sky-50 text-sky-700' => ! $isPdf,
                        ])>{{ $isPdf ? 'PDF' : strtoupper(substr($extension, 0, 3)) }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-800">{{ $file['name'] ?? '-' }}</span>
                            <span class="block text-xs font-medium text-slate-500">{{ $formattedSize }}</span>
                        </span>
                    </div>
                </div>

                <button type="button" class="mt-5 inline-flex size-11 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" data-existing-attachment-remove aria-label="Hapus lampiran">
                    <input type="hidden" value="{{ $file['id'] ?? '' }}" data-existing-attachment-id>
                    <x-flux.icon name="trash" class="size-5" />
                </button>
            </div>
        @endforeach
    </div>

    @if ($showCreateControls)
        <button type="button" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-add-attachment-row>
            <x-flux.icon name="plus" class="size-5" />
            Tambah Lampiran
        </button>

        <template data-attachment-row-template>
            <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_minmax(260px,0.9fr)_auto]" data-attachment-row>
                <input type="hidden" name="attachment_orders[]" value="" data-attachment-order-input>
                <label class="block min-w-0">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Dokumen/Lampiran</span>
                    <input
                        type="text"
                        name="attachment_titles[]"
                        placeholder="Masukkan nama lampiran"
                        required
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                    >
                </label>

                <label class="block min-w-0">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">File</span>
                    <span class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2 transition hover:border-sky-300 hover:bg-sky-50" data-attachment-picker>
                        <span class="grid size-9 shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-slate-500" data-attachment-file-icon>
                            <x-flux.icon name="arrow-up-tray" class="size-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-700" data-attachment-file-name>Pilih file PDF</span>
                            <span class="block text-xs font-medium text-slate-500" data-attachment-file-meta>Maksimal 10 MB</span>
                        </span>
                    </span>
                    <input type="file" name="attachments[]" accept=".pdf,application/pdf" class="sr-only" data-attachment-file-input required>
                </label>

                <button type="button" class="mt-5 inline-flex size-11 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" data-remove-attachment-row aria-label="Hapus lampiran">
                    <x-flux.icon name="trash" class="size-5" />
                </button>
            </div>
        </template>
    @endif
</div>

@once
    <script>
        (() => {
            const formatFileSize = (file) => {
                const sizeKb = Math.ceil(file.size / 1024);

                return sizeKb >= 1024
                    ? `${(sizeKb / 1024).toFixed(1)} MB`
                    : `${sizeKb} KB`;
            };

            const renderSelectedFile = (input) => {
                const row = input.closest('[data-attachment-row]');
                const file = input.files?.[0];
                const name = row?.querySelector('[data-attachment-file-name]');
                const meta = row?.querySelector('[data-attachment-file-meta]');
                const icon = row?.querySelector('[data-attachment-file-icon]');

                if (!row || !name || !meta || !icon) {
                    return;
                }

                if (!file) {
                    name.textContent = 'Pilih file PDF';
                    meta.textContent = 'Maksimal 10 MB';
                    icon.className = 'grid size-9 shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-slate-500';
                    icon.innerHTML = '<svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 7.5 12 3m0 0 4.5 4.5M12 3v13.5" /></svg>';

                    return;
                }

                name.textContent = file.name;
                meta.textContent = formatFileSize(file);
                icon.className = 'grid size-9 shrink-0 place-items-center rounded-md border border-red-100 bg-red-50 text-[10px] font-bold text-red-600';
                icon.textContent = 'PDF';
            };

            const orderOffsetFor = (root) => {
                const form = root?.closest('[data-submitted-attachment-form]');
                
                const submittedItems = form?.querySelector('[data-submitted-attachment-items]');

                if (!submittedItems || submittedItems.contains(root)) {
                    return 0;
                }

                return submittedItems.querySelectorAll('[data-existing-attachment-row]').length;
            };

            const renumberRows = (root) => {
                const offset = orderOffsetFor(root);

                root?.querySelectorAll('[data-attachment-order-input]').forEach((input, index) => {
                    input.value = offset + index + 1;
                });
            };

            const handleAttachmentListClick = (event) => {
                const addButton = event.target.closest('[data-add-attachment-row]');

                if (addButton) {
                    const root = addButton.closest('[data-attachment-list]');
                    const list = root?.querySelector('[data-attachment-items]');
                    const template = root?.querySelector('[data-attachment-row-template]');

                    if (!list || !template) {
                        return;
                    }

                    list.insertAdjacentHTML('beforeend', template.innerHTML);
                    renumberRows(root);
                    list.lastElementChild?.querySelector('input[name="attachment_titles[]"]')?.focus();
                    return;
                }

                const removeButton = event.target.closest('[data-remove-attachment-row]');

                if (removeButton) {
                    const root = removeButton.closest('[data-attachment-list]');
                    removeButton.closest('[data-attachment-row]')?.remove();
                    renumberRows(root);
                    return;
                }

                const existingRemoveButton = event.target.closest('[data-existing-attachment-remove]');

                if (existingRemoveButton) {
                    const row = existingRemoveButton.closest('[data-existing-attachment-row]');
                    const root = existingRemoveButton.closest('[data-attachment-list]');
                    const fileId = row?.querySelector('[data-existing-attachment-id]')?.value;

                    if (fileId) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'remove_existing_files[]';
                        input.value = fileId;
                        root?.append(input);
                    }

                    row?.remove();
                    renumberRows(root);
                }
            };

            const handleAttachmentListChange = (event) => {
                const input = event.target.closest('[data-attachment-file-input]');

                if (input) {
                    renderSelectedFile(input);
                }
            };

            const initAttachmentLists = () => {
                document.querySelectorAll('[data-attachment-list]').forEach(renumberRows);
            };

            if (window.handleAttachmentListClick) {
                document.removeEventListener('click', window.handleAttachmentListClick);
            }

            if (window.handleAttachmentListChange) {
                document.removeEventListener('change', window.handleAttachmentListChange);
            }

            window.handleAttachmentListClick = handleAttachmentListClick;
            window.handleAttachmentListChange = handleAttachmentListChange;
            window.renumberAttachmentRows = renumberRows;
            window.initAttachmentLists = initAttachmentLists;

            document.addEventListener('click', window.handleAttachmentListClick);
            document.addEventListener('change', window.handleAttachmentListChange);
            document.addEventListener('DOMContentLoaded', window.initAttachmentLists);
            document.addEventListener('lived', window.initAttachmentLists);

            initAttachmentLists();
        })();
    </script>
@endonce
