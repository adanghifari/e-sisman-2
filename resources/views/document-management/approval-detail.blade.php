<x-layouts.app :title="__('Detail Approval Dokumen')">
    @php
        $levelKey = $document->documentLevel?->kode ?? 'level-3';
        $levelNumbers = [
            'level-1' => 'I',
            'level-2' => 'II',
            'level-3' => 'III',
            'level-4' => 'IV',
        ];
        $isLevelOne = $levelKey === 'level-1';
        $statusCode = $activeApproval?->status?->kode_status ?? $document->status?->nama_status ?? '-';
        $statusLabel = $activeApproval?->status?->nama_status ?? $document->status?->nama_status ?? '-';
        $isObsoleteRequest = $document->request_type === 'obsolete';
        $showSourceFiles = $document->status?->nama_status === \App\Models\StatusDocument::PROPOSED;
        $printoutTitle = $showSourceFiles ? 'Printout PDF Sementara' : 'Printout PDF Final';
        $printoutDescription = $showSourceFiles
            ? ($isLevelOne
                ? 'Preview dinamis. Versi final akan memakai cover, kop, footer, dan lampiran tanpa lembar pengesahan.'
                : 'Preview dinamis. Lembar pengesahan akan tersedia setelah semua approval selesai.')
            : ($isLevelOne
                ? 'Versi final lengkap dengan cover, kop, footer, dan lampiran tanpa lembar pengesahan.'
                : 'Versi final lengkap dengan cover, kop, footer, lembar pengesahan, dan lampiran.');
        $printoutVersion = collect([
            $document->updated_at?->timestamp,
            $document->files->max(fn ($file) => $file->updated_at?->timestamp),
            $document->files->max('id'),
            $document->finalArtifacts->max('id'),
        ])->filter()->implode('-');
        $ownerLabel = $isObsoleteRequest ? 'Pengaju Awal Dokumen' : ($isLevelOne ? 'Penyusun Dokumen' : 'Penyusun Pemilik Proses');
        $contentSectionTitle = match (true) {
            $isObsoleteRequest => 'Dokumen yang Akan Diobsoletekan',
            $levelKey === 'level-4' => 'Dokumen Revisi',
            default => 'Isi Dokumen',
        };
        $approvalFlowLabel = $approvalFlowDocumentLevel?->nama_dokumen
            ?? $approvalFlowDocumentLevel?->nama_level
            ?? $document->documentLevel?->nama_dokumen
            ?? $document->documentLevel?->nama_level
            ?? '-';
        $approvalFlowDescription = $levelKey === 'level-4' && ($document->revisedFrom?->documentLevel || $document->importedExistingSource?->documentLevel)
            ? 'Mengikuti approval flow dokumen induk: '.$approvalFlowLabel
            : 'Approval Flow '.$approvalFlowLabel;
        $contentFileLabels = [
            'filled_template' => 'Template Dokumen',
            'filled_template_word' => 'Template Dokumen Word',
            'imported_document' => 'Dokumen Import',
            'revision_content' => 'Dokumen Revisi',
            'revision_content_word' => 'Dokumen Revisi Word',
            'revision_form' => 'Lembar Revisi',
            'revision_form_word' => 'Lembar Revisi Word',
            'revision_before' => 'Semula',
            'revision_after' => 'Menjadi',
        ];
        $revisionMainFiles = $levelKey === 'level-4'
            ? collect(['revision_form', 'revision_form_word', 'revision_content', 'revision_content_word'])
                ->map(fn ($type) => $contentFiles
                    ->where('type_file', $type)
                    ->sortByDesc('id')
                    ->first())
                ->filter()
                ->values()
            : collect();
        $otherContentFiles = $levelKey === 'level-4'
            ? $contentFiles
                ->reject(fn ($file) => in_array($file->type_file, ['revision_content', 'revision_content_word', 'revision_form', 'revision_form_word'], true))
                ->values()
            : $contentFiles;
        $revisionFileGroups = $levelKey === 'level-4'
            ? collect([
                [
                    'title' => 'Lembar Revisi',
                    'description' => 'File PDF dan Word lembar revisi yang diajukan user.',
                    'pdf_label' => 'Upload Lembar Revisi PDF',
                    'word_label' => 'Upload Lembar Revisi Word',
                    'pdf' => $contentFiles->where('type_file', 'revision_form')->sortByDesc('id')->first(),
                    'word' => $contentFiles->where('type_file', 'revision_form_word')->sortByDesc('id')->first(),
                ],
                [
                    'title' => 'Dokumen Revisi',
                    'description' => 'File PDF dan Word dokumen revisi yang diajukan user.',
                    'pdf_label' => 'Upload Dokumen Revisi PDF',
                    'word_label' => 'Upload Dokumen Revisi Word',
                    'pdf' => $contentFiles->where('type_file', 'revision_content')->sortByDesc('id')->first(),
                    'word' => $contentFiles->where('type_file', 'revision_content_word')->sortByDesc('id')->first(),
                ],
            ])->filter(fn (array $group): bool => $group['pdf'] !== null || $group['word'] !== null)->values()
            : collect();
        $templatePdfFile = $levelKey !== 'level-4'
            ? $contentFiles->where('type_file', 'filled_template')->sortByDesc('id')->first()
            : null;
        $templateWordFile = $levelKey !== 'level-4'
            ? $contentFiles->where('type_file', 'filled_template_word')->sortByDesc('id')->first()
            : null;
        $standaloneContentFiles = $levelKey !== 'level-4'
            ? $contentFiles
                ->reject(fn ($file) => in_array($file->type_file, ['filled_template', 'filled_template_word'], true))
                ->values()
            : collect();
        $readonlyInput = 'h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-600 outline-none';
        $departmentOptions = collect($departments ?? [])
            ->map(fn ($department) => [
                'value' => $department->id,
                'label' => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department,
            ])
            ->values();
        $filePreviewVersion = fn ($file) => collect([
            $file->updated_at?->timestamp,
            $file->id,
        ])->filter()->implode('-');
        $metadataEditorStartsOpen = old('_update_scope') === 'metadata';
        $fileEditorStartsOpen = old('_update_scope') === 'files'
            && collect($errors->getMessages())->keys()->contains(fn ($key) => str_starts_with($key, 'replacement_files.'));
    @endphp

    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700">Home</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.inbox') }}" class="transition hover:text-sky-700">Inbox Approval</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $document->nomor_dokumen ?: 'Detail Dokumen' }}</span>
        </nav>

        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">
                    {{ $isObsoleteRequest ? 'Pengajuan Obsolete Dokumen' : 'Detail Dokumen Level '.($levelNumbers[$levelKey] ?? '-') }}
                </h1>
                <p class="mt-2 text-base font-medium text-slate-500">{{ $document->nama_dokumen }}</p>
            </div>

            <x-ui.status-badge :label="$statusLabel" :tone="$statusCode === 'PENDING' ? 'amber' : ($statusCode === 'APPROVED' ? 'emerald' : 'red')" class="mt-1" />
        </div>

        @if ($isObsoleteRequest)
            <section class="rounded-lg border border-red-100 bg-red-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white text-red-600 ring-1 ring-red-100">
                        <x-flux.icon name="archive-box-x-mark" class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-red-950">Review Pengajuan Obsolete</h2>
                        <p class="mt-1 text-sm font-medium leading-6 text-red-800">
                            Approval ini akan mengubah dokumen master terkait menjadi obsolete setelah seluruh tahap disetujui.
                        </p>
                    </div>
                </div>
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_440px]">
            <div class="space-y-6">
                <x-documents.form-section title="Informasi Dokumen">
                    @if ($canUpdateSubmittedDocument)
                        <x-slot name="actions">
                            <div class="{{ $metadataEditorStartsOpen ? 'hidden' : '' }}" data-submitted-document-readonly-action>
                                <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-submitted-document-edit-open>
                                    <x-flux.icon name="pencil-square" class="size-4" />
                                    Edit Informasi
                                </button>
                            </div>
                        </x-slot>
                    @endif

                    <div class="px-6 py-4" data-submitted-document-editor>
                        <dl class="divide-y divide-slate-100 {{ $metadataEditorStartsOpen ? 'hidden' : '' }}" data-submitted-document-readonly>
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Nama Dokumen</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $document->nama_dokumen }}</dd>
                            </div>
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Level Dokumen</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $document->documentLevel?->nama_level }} : {{ \Illuminate\Support\Str::after($document->documentLevel?->nama_dokumen ?? '', ': ') }}</dd>
                            </div>
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Proses Bisnis</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $document->businessProcess?->nama_proses_bisnis ?? '-' }}</dd>
                            </div>
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Department Terkait</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $document->departments->map(fn ($department) => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department)->implode(', ') ?: '-' }}</dd>
                            </div>
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Proses / Fungsi</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $document->businessFunction?->nama_proses_fungsi ?? '-' }}</dd>
                            </div>
                        </dl>

                        @if ($canUpdateSubmittedDocument)
                            <form method="POST" action="{{ route('documents.approval.update-submitted', $document) }}" class="{{ $metadataEditorStartsOpen ? '' : 'hidden' }} space-y-5" data-submitted-document-form>
                                @csrf
                                <input type="hidden" name="_update_scope" value="metadata">

                                <label class="block">
                                    <span class="mb-2 block text-base font-medium text-slate-500">Nama Dokumen</span>
                                    <input type="text" name="nama_dokumen" value="{{ old('nama_dokumen', $document->nama_dokumen) }}" required class="h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base font-medium text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100">
                                    @error('nama_dokumen')
                                        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                </label>

                                @if ($levelKey !== 'level-1')
                                    <div class="grid gap-5 md:grid-cols-2">
                                        <div class="block">
                                            <span class="mb-2 block text-base font-medium text-slate-500">Proses Bisnis</span>
                                            <div class="flex min-h-12 items-center rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-700">
                                                {{ $document->businessProcess?->nama_proses_bisnis ?? '-' }}
                                            </div>
                                        </div>

                                        <div class="block">
                                            <span class="mb-2 block text-base font-medium text-slate-500">Proses / Fungsi</span>
                                            <div class="flex min-h-12 items-center rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-700">
                                                {{ $document->businessFunction?->nama_proses_fungsi ?? '-' }}
                                            </div>
                                        </div>

                                        <x-ui.multi-select
                                            label="Department Terkait"
                                            name="department_ids"
                                            :options="$departmentOptions"
                                            :selected="old('department_ids', $document->departments->pluck('id')->all())"
                                            selected-placeholder="Tambah Department"
                                            required
                                        />

                                        @if ($levelKey === 'level-3')
                                            <div class="block md:col-span-2">
                                                <span class="mb-2 block text-base font-medium text-slate-500">Dokumen Acuan</span>
                                                <div class="flex min-h-12 items-center rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-700">
                                                    @if ($referenceRelation = $document->procedureReferenceRelation())
                                                        {{ $referenceRelation->target()?->nomor_dokumen ?: '-' }} - {{ $referenceRelation->target()?->nama_dokumen ?? '-' }}
                                                    @else
                                                        -
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <div class="flex flex-wrap justify-end gap-3">
                                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-submitted-document-edit-cancel>Batal</button>
                                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-700">Save</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </x-documents.form-section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">{{ $ownerLabel }}</h2>
                    </div>

                    <div class="space-y-4 px-6 py-6">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $isObsoleteRequest ? 'Pengaju Awal Dokumen' : 'Pengisi Form' }}</span>
                            <div class="mt-2 flex items-center gap-2.5">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">
                                    {{ ($isObsoleteRequest ? $document->revisedFrom?->creator : $document->creator)?->initials() ?? '-' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ ($isObsoleteRequest ? $document->revisedFrom?->creator : $document->creator)?->name ?? '-' }}</span>
                                    <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">{{ ($isObsoleteRequest ? $document->revisedFrom?->creator : $document->creator)?->jabatan ?: (($isObsoleteRequest ? $document->revisedFrom?->creator : $document->creator)?->email ?? '-') }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3">
                            <span class="block text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Penyusun Resmi</span>
                            <div class="mt-2 flex items-center gap-2.5">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">
                                    {{ $document->officialPreparer?->initials() ?? '-' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ $document->officialPreparer?->name ?? '-' }}</span>
                                    <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">{{ $document->officialPreparer?->jabatan ?: $document->officialPreparer?->email }}</span>
                                </span>
                            </div>
                        </div>

                        @if ($isObsoleteRequest)
                            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-3">
                                <span class="block text-[11px] font-semibold uppercase tracking-wide text-red-700">Pengaju Obsolete</span>
                                <div class="mt-2 flex items-center gap-2.5">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-bold text-red-700 ring-1 ring-red-100">
                                        {{ $document->creator?->initials() ?? '-' }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ $document->creator?->name ?? '-' }}</span>
                                        <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">{{ $document->creator?->jabatan ?: $document->creator?->email }}</span>
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                </section>

                @if ($isObsoleteRequest)
                    <x-documents.form-section title="Alasan Obsolete" icon="archive-box-x-mark">
                        <div class="px-6 py-6">
                            <div class="rounded-lg border border-red-100 bg-red-50 px-4 py-4">
                                <p class="text-sm font-semibold leading-6 text-red-900">
                                    {{ $document->catatan_revisi ?: '-' }}
                                </p>
                            </div>
                        </div>
                    </x-documents.form-section>
                @endif

                @if (! $isObsoleteRequest)
                    <x-documents.form-section :title="$printoutTitle" icon="document-check">
                        <div class="space-y-4 px-6 py-6">
                            @if ($canPreviewGeneratedPrintout)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <div class="border-b border-slate-200 bg-white px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $printoutTitle }}</p>
                                            <p class="text-xs font-medium text-slate-500">{{ $printoutDescription }}</p>
                                        </div>
                                    </div>

                                    <x-documents.lazy-pdf-preview :src="route('documents.approval.generated.show', [$document, 'v' => $printoutVersion]).'#toolbar=0&view=FitH&navpanes=0'" />
                                </section>
                            @else
                                <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                    {{ $printoutTitle }} belum tersedia karena file sumber dokumen belum lengkap.
                                </p>
                            @endif
                        </div>
                    </x-documents.form-section>
                @endif

                @if ($showSourceFiles)
                    <x-documents.form-section :title="$contentSectionTitle" icon="document-text">
                        <div class="space-y-4 px-6 py-6">
                            @if ($isObsoleteRequest)
                                @forelse ($obsoleteSourceContentFiles as $file)
                                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                        <div class="border-b border-slate-200 bg-white px-4 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-slate-900">{{ $file->original_file_name }}</p>
                                                <p class="text-xs font-medium text-slate-500">{{ $contentFileLabels[$file->type_file] ?? strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
                                            </div>
                                        </div>

                                        <x-documents.lazy-pdf-preview :src="route('documents.approval.files.preview', [$document, $file, 'v' => $filePreviewVersion($file)]).'#toolbar=0&view=FitH&navpanes=0'" />
                                    </section>
                                @empty
                                    <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                        Belum ada file isi dokumen.
                                    </p>
                                @endforelse
                            @elseif ($levelKey === 'level-4')
                            @if ($revisionFileGroups->isNotEmpty())
                                @foreach ($revisionFileGroups as $revisionFileGroup)
                                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                        <form method="POST" action="{{ route('documents.approval.update-submitted', $document) }}" enctype="multipart/form-data" data-submitted-file-form>
                                            @csrf
                                            <input type="hidden" name="_update_scope" value="files">

                                            <div class="border-b border-slate-200 bg-white px-4 py-3">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-bold text-slate-900">{{ $revisionFileGroup['title'] }}</p>
                                                        <p class="text-xs font-medium text-slate-500">{{ $revisionFileGroup['description'] }}</p>
                                                    </div>
                                                    @if ($canUpdateSubmittedDocument)
                                                        <div class="{{ $fileEditorStartsOpen ? 'hidden' : 'flex' }} shrink-0 flex-wrap gap-2" data-file-readonly-action>
                                                            <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-file-edit-open>Edit</button>
                                                        </div>
                                                        <div class="{{ $fileEditorStartsOpen ? 'flex' : 'hidden' }} shrink-0 flex-wrap gap-2" data-file-edit-control>
                                                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-600 transition hover:border-red-200 hover:bg-red-100" data-file-edit-cancel aria-label="Batal edit">
                                                                <x-flux.icon name="x-mark" class="size-4" />
                                                            </button>
                                                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-sky-600 px-3 text-xs font-bold text-white transition hover:bg-sky-700">Save</button>
                                                        </div>
                                                    @endif
                                                </div>

                                                @if ($fileEditorStartsOpen)
                                                    <div class="mt-3 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600">
                                                        {{ collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'replacement_files.'))->flatten()->first() }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="grid gap-4 p-4 lg:grid-cols-2">
                                                @foreach ([['file' => $revisionFileGroup['pdf'], 'label' => $revisionFileGroup['pdf_label'], 'icon' => asset('image/icon_PDF.webp'), 'accept' => '.pdf,application/pdf'], ['file' => $revisionFileGroup['word'], 'label' => $revisionFileGroup['word_label'], 'icon' => asset('image/icon_word.webp'), 'accept' => '.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document']] as $revisionFile)
                                                    @php
                                                        $file = $revisionFile['file'];
                                                        $fileErrorKey = $file ? "replacement_files.{$file->id}" : null;
                                                    @endphp
                                                    <div class="min-w-0 rounded-lg border {{ $fileErrorKey && $errors->has($fileErrorKey) ? 'border-red-200 bg-red-50/20' : 'border-slate-200 bg-white' }}" data-file-edit-item>
                                                        <div class="flex min-h-20 items-start justify-between gap-3 px-4 py-3">
                                                            <div class="flex min-w-0 items-start gap-3">
                                                                <span class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white p-1.5 shadow-sm">
                                                                    <img src="{{ $revisionFile['icon'] }}" alt="{{ $revisionFile['label'] }}" class="max-h-full max-w-full object-contain">
                                                                </span>
                                                                <span class="min-w-0">
                                                                    <span class="block text-xs font-bold uppercase tracking-wide text-slate-500">{{ $revisionFile['label'] }}</span>
                                                                    <span class="mt-1 block truncate text-sm font-bold text-slate-900" data-selected-file-name data-original-file-name="{{ $file?->original_file_name }}">{{ $file?->original_file_name ?? 'Belum ada file' }}</span>
                                                                </span>
                                                            </div>

                                                            @if ($canUpdateSubmittedDocument && $file)
                                                                <div class="{{ $fileEditorStartsOpen ? 'flex' : 'hidden' }} shrink-0 flex-wrap gap-2" data-file-edit-control>
                                                                    <a href="{{ route('documents.approval.files.show', [$document, $file]) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700">Download</a>
                                                                    <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-file-picker-button>Perbarui</button>
                                                                </div>
                                                                <input type="file" name="replacement_files[{{ $file->id }}]" accept="{{ $revisionFile['accept'] }}" class="sr-only" data-file-picker-input>
                                                            @endif
                                                        </div>

                                                        @if ($fileErrorKey)
                                                            @error($fileErrorKey)
                                                                <p class="border-t border-red-100 px-4 py-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                                                            @enderror
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>

                                            @if ($revisionFileGroup['pdf'])
                                                <x-documents.lazy-pdf-preview
                                                    :src="route('documents.approval.files.preview', [$document, $revisionFileGroup['pdf'], 'v' => $filePreviewVersion($revisionFileGroup['pdf'])]).'#toolbar=0&view=FitH&navpanes=0'"
                                                    height-class="h-[620px] 2xl:h-[72vh]"
                                                />
                                            @endif
                                        </form>
                                    </section>
                                @endforeach
                            @endif

                            @foreach ($otherContentFiles as $file)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <div class="border-b border-slate-200 bg-white px-4 py-3">
                                        <form method="POST" action="{{ route('documents.approval.update-submitted', $document) }}" enctype="multipart/form-data" class="flex flex-wrap items-start justify-between gap-3" data-submitted-file-form>
                                            @csrf
                                            <input type="hidden" name="_update_scope" value="files">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-slate-900" data-selected-file-name>{{ $file->original_file_name }}</p>
                                                <p class="text-xs font-medium text-slate-500">{{ $contentFileLabels[$file->type_file] ?? strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
                                            </div>
                                            @if ($canUpdateSubmittedDocument)
                                                <div class="flex shrink-0 flex-wrap gap-2" data-file-readonly-action>
                                                    <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-file-edit-open>Edit</button>
                                                </div>
                                                <div class="hidden shrink-0 flex-wrap gap-2" data-file-edit-control>
                                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-600 transition hover:border-red-200 hover:bg-red-100" data-file-edit-cancel aria-label="Batal edit">
                                                        <x-flux.icon name="x-mark" class="size-4" />
                                                    </button>
                                                    <a href="{{ route('documents.approval.files.show', [$document, $file]) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700">Download</a>
                                                    <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-file-picker-button>Perbarui</button>
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-sky-600 px-3 text-xs font-bold text-white transition hover:bg-sky-700">Save</button>
                                                </div>
                                                <input type="file" name="replacement_files[{{ $file->id }}]" accept=".pdf,application/pdf" class="sr-only" data-file-picker-input>
                                            @endif
                                        </form>
                                    </div>

                                    <x-documents.lazy-pdf-preview :src="route('documents.approval.files.preview', [$document, $file, 'v' => $filePreviewVersion($file)]).'#toolbar=0&view=FitH&navpanes=0'" />
                                </section>
                            @endforeach

                            @if ($revisionFileGroups->isEmpty() && $otherContentFiles->isEmpty())
                                <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                    Belum ada file isi dokumen.
                                </p>
                            @endif
                        @else
                            @if ($templatePdfFile || $templateWordFile)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <form method="POST" action="{{ route('documents.approval.update-submitted', $document) }}" enctype="multipart/form-data" data-submitted-file-form>
                                        @csrf
                                        <input type="hidden" name="_update_scope" value="files">

                                        <div class="border-b border-slate-200 bg-white px-4 py-3">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-bold text-slate-900">Template Dokumen yang Sudah Diisi</p>
                                                    <p class="text-xs font-medium text-slate-500">File PDF dan Word yang diajukan user.</p>
                                                </div>
                                                @if ($canUpdateSubmittedDocument)
                                                    <div class="{{ $fileEditorStartsOpen ? 'hidden' : 'flex' }} shrink-0 flex-wrap gap-2" data-file-readonly-action>
                                                        <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-file-edit-open>Edit</button>
                                                    </div>
                                                    <div class="{{ $fileEditorStartsOpen ? 'flex' : 'hidden' }} shrink-0 flex-wrap gap-2" data-file-edit-control>
                                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-600 transition hover:border-red-200 hover:bg-red-100" data-file-edit-cancel aria-label="Batal edit">
                                                            <x-flux.icon name="x-mark" class="size-4" />
                                                        </button>
                                                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-sky-600 px-3 text-xs font-bold text-white transition hover:bg-sky-700">Save</button>
                                                    </div>
                                                @endif
                                            </div>

                                            @if ($fileEditorStartsOpen)
                                                <div class="mt-3 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600">
                                                    {{ collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'replacement_files.'))->flatten()->first() }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="grid gap-4 p-4 lg:grid-cols-2">
                                            @foreach ([['file' => $templatePdfFile, 'label' => 'Upload Template Terisi PDF', 'icon' => asset('image/icon_PDF.webp'), 'accept' => '.pdf,application/pdf', 'can_preview' => true], ['file' => $templateWordFile, 'label' => 'Upload Template Terisi Word', 'icon' => asset('image/icon_word.webp'), 'accept' => '.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'can_preview' => false]] as $templateFile)
                                                @php
                                                    $file = $templateFile['file'];
                                                    $fileErrorKey = $file ? "replacement_files.{$file->id}" : null;
                                                @endphp
                                                <div class="min-w-0 rounded-lg border {{ $fileErrorKey && $errors->has($fileErrorKey) ? 'border-red-200 bg-red-50/20' : 'border-slate-200 bg-white' }}" data-file-edit-item>
                                                    <div class="flex min-h-20 items-start justify-between gap-3 px-4 py-3">
                                                        <div class="flex min-w-0 items-start gap-3">
                                                            <span class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white p-1.5 shadow-sm">
                                                                <img src="{{ $templateFile['icon'] }}" alt="{{ $templateFile['label'] }}" class="max-h-full max-w-full object-contain">
                                                            </span>
                                                            <span class="min-w-0">
                                                                <span class="block text-xs font-bold uppercase tracking-wide text-slate-500">{{ $templateFile['label'] }}</span>
                                                                <span class="mt-1 block truncate text-sm font-bold text-slate-900" data-selected-file-name data-original-file-name="{{ $file?->original_file_name }}">{{ $file?->original_file_name ?? 'Belum ada file' }}</span>
                                                            </span>
                                                        </div>

                                                        @if ($canUpdateSubmittedDocument && $file)
                                                            <div class="{{ $fileEditorStartsOpen ? 'flex' : 'hidden' }} shrink-0 flex-wrap gap-2" data-file-edit-control>
                                                                <a href="{{ route('documents.approval.files.show', [$document, $file]) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700">Download</a>
                                                                <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-file-picker-button>Perbarui</button>
                                                            </div>
                                                            <input type="file" name="replacement_files[{{ $file->id }}]" accept="{{ $templateFile['accept'] }}" class="sr-only" data-file-picker-input>
                                                        @endif
                                                    </div>

                                                    @if ($fileErrorKey)
                                                        @error($fileErrorKey)
                                                            <p class="border-t border-red-100 px-4 py-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                                                        @enderror
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        @if ($templatePdfFile)
                                            <x-documents.lazy-pdf-preview :src="route('documents.approval.files.preview', [$document, $templatePdfFile, 'v' => $filePreviewVersion($templatePdfFile)]).'#toolbar=0&view=FitH&navpanes=0'" />
                                        @endif
                                    </form>
                                </section>
                            @endif

                            @foreach ($standaloneContentFiles as $file)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <div class="border-b border-slate-200 bg-white px-4 py-3">
                                        <form method="POST" action="{{ route('documents.approval.update-submitted', $document) }}" enctype="multipart/form-data" class="flex flex-wrap items-start justify-between gap-3" data-submitted-file-form>
                                            @csrf
                                            <input type="hidden" name="_update_scope" value="files">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-slate-900" data-selected-file-name data-original-file-name="{{ $file->original_file_name }}">{{ $file->original_file_name }}</p>
                                                <p class="text-xs font-medium text-slate-500">{{ $contentFileLabels[$file->type_file] ?? strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
                                            </div>
                                            @if ($canUpdateSubmittedDocument)
                                                <div class="flex shrink-0 flex-wrap gap-2" data-file-readonly-action>
                                                    <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-file-edit-open>Edit</button>
                                                </div>
                                                <div class="hidden shrink-0 flex-wrap gap-2" data-file-edit-control>
                                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-100 bg-red-50 text-red-600 transition hover:border-red-200 hover:bg-red-100" data-file-edit-cancel aria-label="Batal edit">
                                                        <x-flux.icon name="x-mark" class="size-4" />
                                                    </button>
                                                    <a href="{{ route('documents.approval.files.show', [$document, $file]) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700">Download</a>
                                                    <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700" data-file-picker-button>Perbarui</button>
                                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-sky-600 px-3 text-xs font-bold text-white transition hover:bg-sky-700">Save</button>
                                                </div>
                                                <input type="file" name="replacement_files[{{ $file->id }}]" accept=".pdf,application/pdf" class="sr-only" data-file-picker-input>
                                            @endif
                                        </form>
                                    </div>

                                    <x-documents.lazy-pdf-preview :src="route('documents.approval.files.preview', [$document, $file, 'v' => $filePreviewVersion($file)]).'#toolbar=0&view=FitH&navpanes=0'" />
                                </section>
                            @endforeach

                            @if (! $templatePdfFile && ! $templateWordFile && $standaloneContentFiles->isEmpty())
                                <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                    Belum ada file isi dokumen.
                                </p>
                            @endif
                            @endif
                        </div>
                    </x-documents.form-section>

                    <section class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm" data-attachment-editor>
                        <div class="border-b border-slate-200 px-6 py-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                                        <x-flux.icon name="paper-clip" class="size-5" />
                                    </span>
                                    <h2 class="text-lg font-bold text-slate-900">Lampiran</h2>
                                </div>

                                @if ($canUpdateSubmittedDocument)
                                    <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-attachment-edit-open>
                                        <x-flux.icon name="pencil-square" class="size-4" />
                                        <span data-attachment-edit-label>Edit</span>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-3 px-6 py-6">
                            @if ($canUpdateSubmittedDocument)
                                <form method="POST" action="{{ route('documents.approval.update-submitted', $document) }}" enctype="multipart/form-data" class="space-y-4" data-submitted-attachment-form>
                                    @csrf
                                    <input type="hidden" name="_update_scope" value="files">
                                    <input type="hidden" name="sync_existing_attachment_inclusion" value="1">

                                    @php
                                        $submittedSourceAttachmentFiles = $attachmentFiles
                                            ->filter(fn ($file) => filled($file->source_file_id))
                                            ->values();
                                        $submittedNewAttachmentFiles = $attachmentFiles
                                            ->reject(fn ($file) => filled($file->source_file_id))
                                            ->values();
                                    @endphp

                                    <div class="space-y-3" data-submitted-attachment-items>
                                        @foreach ($submittedSourceAttachmentFiles->merge($submittedNewAttachmentFiles) as $file)
                                            @php
                                                $isSourceAttachment = filled($file->source_file_id);
                                            @endphp

                                            <div class="flex gap-3 rounded-lg border border-slate-200 bg-white p-4 transition" data-existing-attachment-row @if ($isSourceAttachment) data-source-attachment-row @endif draggable="false">
                                                @if ($isSourceAttachment)
                                                    <label class="mt-5 hidden w-36 shrink-0 cursor-pointer place-items-center gap-2" data-attachment-edit-control>
                                                        <span class="grid h-5 w-full place-items-center">
                                                            <input type="checkbox" name="included_existing_attachment_ids[]" value="{{ $file->id }}" checked class="size-5 rounded border-slate-300 text-sky-600 focus:ring-sky-200" data-source-attachment-include>
                                                        </span>
                                                        <span class="inline-flex w-32 justify-center whitespace-nowrap rounded-full bg-emerald-50 px-3 py-1 text-center text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-100" data-source-attachment-state>Dicantumkan</span>
                                                    </label>
                                                @else
                                                    <button type="button" class="mt-5 hidden size-11 shrink-0 cursor-grab items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 active:cursor-grabbing" data-attachment-drag-handle data-attachment-edit-control draggable="true" aria-label="Geser urutan lampiran">
                                                        <x-flux.icon name="bars-3" class="size-5" />
                                                    </button>
                                                @endif
                                                <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-[minmax(0,1fr)_minmax(220px,0.7fr)_auto]">
                                                    @unless ($isSourceAttachment)
                                                        <input type="hidden" name="existing_attachment_orders[{{ $file->id }}]" value="{{ $file->attachment_order ?? $loop->iteration }}" data-attachment-order-input>
                                                    @endunless
                                                    <label class="block min-w-0">
                                                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Dokumen/Lampiran</span>
                                                        <input type="text" name="existing_attachment_titles[{{ $file->id }}]" value="{{ $file->attachment_title ?: $file->original_file_name }}" readonly class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100" data-attachment-title-input>
                                                    </label>

                                                    <div class="min-w-0">
                                                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">File</span>
                                                        <div class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                                            <span class="grid size-9 shrink-0 place-items-center rounded-md border border-red-100 bg-red-50 text-[10px] font-bold text-red-600">PDF</span>
                                                            <span class="min-w-0">
                                                                <span class="block truncate text-sm font-semibold text-slate-800" data-selected-file-name data-original-file-name="{{ $file->original_file_name }}">{{ $file->original_file_name }}</span>
                                                                <span class="block text-xs font-medium text-slate-500">{{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB</span>
                                                            </span>
                                                        </div>
                                                        <input type="file" name="replacement_attachments[{{ $file->id }}]" accept=".pdf,application/pdf" class="sr-only" data-file-picker-input>
                                                    </div>

                                                    <div class="mt-5 hidden flex-wrap gap-2" data-file-edit-control>
                                                        <a href="{{ route('documents.approval.files.show', [$document, $file]) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700">
                                                            Download
                                                        </a>
                                                        <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700" data-file-picker-button>
                                                            Perbarui
                                                        </button>
                                                        @unless ($isSourceAttachment)
                                                            <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" data-submitted-attachment-remove>
                                                                <input type="hidden" value="{{ $file->id }}" data-existing-attachment-id>
                                                                Hapus
                                                            </button>
                                                        @endunless
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="hidden" data-attachment-edit-control>
                                        <x-documents.attachment-list :existing-files="collect()" />
                                    </div>

                                    @error('attachments.*')
                                        <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                    @error('attachment_titles.*')
                                        <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror
                                    @error('replacement_attachments.*')
                                        <span class="block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror

                                    <button type="submit" class="hidden h-11 w-full items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-700" data-attachment-edit-control>Save Lampiran</button>
                                </form>
                            @else
                                @forelse ($attachmentFiles as $file)
                                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $file->attachment_title ?: $file->original_file_name }}</p>
                                            <p class="text-xs font-medium text-slate-500">{{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                        Tidak ada lampiran.
                                    </p>
                                @endforelse
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            <aside class="space-y-6 xl:sticky xl:top-8">
                <section class="h-fit overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900">Rincian Dokumen</h2>
                    </div>

                    <div class="space-y-5 px-6 py-6">
                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Nomor Dokumen</span>
                            <input type="text" value="{{ $masterDisplayNumber }}" readonly class="{{ $readonlyInput }}">
                        </label>

                        @if ($document->nomor_lembar_revisi)
                            <label class="block">
                                <span class="mb-2 block text-base font-medium text-slate-500">Nomor Lembar Revisi</span>
                                <input type="text" value="{{ $document->nomor_lembar_revisi }}" readonly class="{{ $readonlyInput }}">
                            </label>
                        @endif

                        <label class="block">
                            <span class="mb-2 block text-base font-medium text-slate-500">Revisi</span>
                            <input type="text" value="{{ $document->formatted_revision }}" readonly class="{{ $readonlyInput }}">
                        </label>

                        <div class="space-y-4 pt-1 text-base font-medium text-slate-500">
                            <div class="flex items-center gap-3">
                                <x-flux.icon name="arrow-path" class="size-6 text-slate-700" />
                                <span>Status Dokumen</span>
                                <span class="ml-auto rounded-full bg-slate-200 px-3 py-1 text-sm font-bold text-slate-700">{{ $document->status?->nama_status ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-flux.icon name="calendar-days" class="size-6 text-slate-700" />
                                <span>Tanggal Pengajuan</span>
                                <span class="ml-auto text-slate-500">{{ $document->submitted_at?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-flux.icon name="calendar" class="size-6 text-slate-700" />
                                <span>Tanggal Terbit</span>
                                <span class="ml-auto text-slate-500">{{ $document->tanggal_terbit?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>
                        </div>

                        @if ($canResubmitRejectedDocument)
                            <a href="{{ route('documents.rejected.resubmit', $document) }}" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-bold leading-5 text-white shadow-sm transition hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-flux.icon name="arrow-uturn-left" class="size-5 shrink-0" />
                                <span>{{ $document->request_type === 'revision' || $document->revised_from !== null ? 'Ajukan Ulang Revisi' : 'Ajukan Ulang Dokumen' }}</span>
                            </a>
                        @endif
                    </div>
                </section>

                @if ($rejectionHistory->isNotEmpty())
                    <section class="overflow-hidden rounded-lg border border-red-100 bg-white shadow-sm">
                        <div class="border-b border-red-100 px-6 py-5">
                            <h3 class="text-sm font-bold text-red-950">Riwayat Penolakan</h3>
                        </div>
                        <div class="space-y-2 px-6 py-5">
                            @foreach ($rejectionHistory as $item)
                                <div class="rounded-lg bg-red-50 px-3 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-red-950">
                                                Transaksi #{{ $item['document_id'] }}
                                            </p>
                                            <p class="mt-1 text-xs font-medium text-red-700">
                                                {{ $item['stage'] }} oleh {{ $item['approver_name'] }}
                                            </p>
                                            @if ($item['responded_at'])
                                                <p class="mt-1 text-xs font-medium text-red-700">
                                                    {{ $item['responded_at']->translatedFormat('d M Y H:i:s') }}
                                                </p>
                                            @endif
                                        </div>
                                        <x-ui.status-badge label="Ditolak" tone="red" class="shrink-0" />
                                    </div>
                                    @if ($item['catatan'])
                                        <p class="mt-2 rounded-md bg-white px-2 py-1 text-xs text-slate-600">{{ $item['catatan'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-5">
                        <h3 class="text-sm font-bold text-slate-900">Riwayat Approver</h3>
                    </div>
                    @php
                        $approvalStageOrdersById = $approvalFlowStages
                            ->mapWithKeys(fn ($stage) => [$stage->id => $stage->stage_order]);
                        $approvalStageOrdersByLabel = $approvalFlowStages
                            ->groupBy(fn ($stage) => $stage->display_label ?: 'Approval')
                            ->map(fn ($stages) => $stages->first()->stage_order);
                        $approvalHistory = $document->approvals
                            ->reject(fn ($approval) => $approval->stages === 'TTD Penyusun Resmi')
                            ->sortBy(fn ($approval) => sprintf(
                                '%04d-%010d-%04d',
                                $approval->stage_order_snapshot
                                    ?? $approvalStageOrdersById->get(
                                        $approval->m_approval_flow_stage_id,
                                        $approvalStageOrdersByLabel->get($approval->stages, 9999),
                                    ),
                                $approval->assigned_at?->timestamp ?? 0,
                                $approval->id,
                            ))
                            ->values();
                        $approvalStatusLabels = [
                            \App\Models\ApprovalStatus::WAITING => 'Menunggu',
                            \App\Models\ApprovalStatus::PENDING => 'Dalam Review',
                            \App\Models\ApprovalStatus::APPROVED => 'Disetujui',
                            \App\Models\ApprovalStatus::REJECTED => 'Ditolak',
                            \App\Models\ApprovalStatus::TERMINATED => 'Dihentikan',
                        ];
                        $approvalStatusTones = [
                            \App\Models\ApprovalStatus::WAITING => 'sky',
                            \App\Models\ApprovalStatus::PENDING => 'amber',
                            \App\Models\ApprovalStatus::APPROVED => 'emerald',
                            \App\Models\ApprovalStatus::REJECTED => 'red',
                            \App\Models\ApprovalStatus::TERMINATED => 'slate',
                        ];
                    @endphp
                    <div class="space-y-2 px-6 py-5">
                        @forelse ($approvalHistory as $approval)
                            @php
                                $approvalStatusCode = $approval->status?->kode_status;
                                $stageOrder = $approval->stage_order_snapshot
                                    ?? $approvalStageOrdersById->get(
                                        $approval->m_approval_flow_stage_id,
                                        $approvalStageOrdersByLabel->get($approval->stages),
                                    );
                                $approvalTimestamp = $approval->responded_at;
                            @endphp
                            <div class="rounded-lg bg-slate-50 px-3 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-100 text-xs font-bold text-sky-700">
                                            {{ $stageOrder ?? '-' }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-800">{{ $approval->approver?->name ?? '-' }}</p>
                                            <p class="mt-1 text-xs font-medium text-slate-500">
                                                {{ $approval->stages ?: 'Approval' }}
                                            </p>
                                            @if ($approvalTimestamp)
                                                <p class="mt-1 text-xs font-medium text-slate-500">
                                                    Diproses pada {{ $approvalTimestamp->translatedFormat('d M Y H:i:s') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <x-ui.status-badge
                                        :label="$approvalStatusLabels[$approvalStatusCode] ?? ($approval->status?->nama_status ?? '-')"
                                        :tone="$approvalStatusTones[$approvalStatusCode] ?? 'sky'"
                                        class="shrink-0"
                                    />
                                </div>
                                @if ($approval->catatan)
                                    <p class="mt-2 rounded-md bg-white px-2 py-1 text-xs text-slate-600">{{ $approval->catatan }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm font-semibold text-slate-500">
                                Belum ada approver yang disimpan.
                            </p>
                        @endforelse
                    </div>
                </section>

                <x-documents.history-section :document-history="$documentHistory" />

                @if ($activeApproval)
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Keputusan Approval</h2>
                        </div>
                        <div class="grid gap-3 px-6 py-5 sm:grid-cols-2">
                            <form method="POST" action="{{ route('documents.approval.approve', $document) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-emerald-600 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                    Approve
                                </button>
                            </form>
                            <button type="button" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-red-600 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-red-700" data-reject-modal-open>
                                Tolak
                            </button>
                        </div>
                    </section>
                @endif

                @if ($canManageApproverAssignment)
                    <section class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">Assign Approver</h2>
                            <p class="mt-2 text-sm font-medium text-slate-500">
                                {{ $approvalFlowDescription }}
                            </p>
                        </div>

                        @if ($approvalFlowStages->isEmpty())
                        <div class="px-6 py-6">
                            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm font-semibold text-slate-500">
                                Belum ada aturan tahap approval.
                            </p>
                        </div>
                    @else
                        @php
                            $hasSavedApprovers = $document->approvals
                                ->reject(fn ($approval) => $approval->stages === 'TTD Penyusun Resmi')
                                ->isNotEmpty();
                            $hasAssignmentErrors = collect($errors->getMessages())
                                ->keys()
                                ->contains(fn ($key) => $key === 'stage_approvers' || str_starts_with($key, 'stage_approvers.'));
                            $hasAssignmentValidationState = old('stage_approvers') !== null || $hasAssignmentErrors;
                            $assignmentStartsReadonly = $hasSavedApprovers && ! $hasAssignmentValidationState;
                        @endphp

                        <form
                            method="POST"
                            action="{{ route('documents.approval.assign', $document) }}"
                            class="space-y-5 px-6 py-6"
                            data-approver-assignment-form
                            data-readonly="{{ $assignmentStartsReadonly ? 'true' : 'false' }}"
                        >
                            @csrf

                            @if ($hasSavedApprovers)
                                <div class="flex justify-end" data-approver-readonly-action @class(['hidden' => ! $assignmentStartsReadonly])>
                                    <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-approver-edit-open>
                                        <x-flux.icon name="pencil-square" class="size-4" />
                                        Edit Approver
                                    </button>
                                </div>
                            @endif

                            @foreach ($approvalFlowStages as $stage)
                                @php
                                    $stageLabel = $stage->display_label ?: 'Approval';
                                    $stageApprovals = $document->approvals
                                        ->filter(fn ($approval) => $approval->m_approval_flow_stage_id === $stage->id
                                            || ($approval->m_approval_flow_stage_id === null && $approval->stages === $stageLabel))
                                        ->values();
                                    $respondedApprovals = $stageApprovals
                                        ->filter(fn ($approval) => $approval->responded_at !== null)
                                        ->values();
                                    $isStageFullyApproved = $stageApprovals->isNotEmpty()
                                        && $stageApprovals->every(fn ($approval) => $approval->status?->kode_status === \App\Models\ApprovalStatus::APPROVED);
                                    $hasOldStageInput = data_get(old('stage_approvers', []), $stage->id) !== null;
                                    $oldApproverIds = collect(old("stage_approvers.{$stage->id}", []))
                                        ->filter()
                                        ->map(fn ($userId) => (int) $userId)
                                        ->values();
                                    $stageApprovers = $hasOldStageInput && ! $isStageFullyApproved
                                        ? $respondedApprovals
                                            ->map(fn ($approval) => [
                                                'user' => $approval->approver,
                                                'status' => $approval->status?->kode_status,
                                                'locked' => true,
                                            ])
                                            ->toBase()
                                            ->merge(
                                                $assignableUsers
                                                    ->whereIn('id', $oldApproverIds->diff($respondedApprovals->pluck('user_id')))
                                                    ->map(fn ($user) => [
                                                        'user' => $user,
                                                        'status' => null,
                                                        'locked' => false,
                                                    ])
                                                    ->values()
                                            )
                                            ->filter(fn ($item) => $item['user'])
                                            ->values()
                                        : $stageApprovals
                                            ->map(fn ($approval) => [
                                                'user' => $approval->approver,
                                                'status' => $approval->status?->kode_status,
                                                'locked' => $approval->responded_at !== null,
                                            ])
                                            ->toBase()
                                            ->filter(fn ($item) => $item['user'])
                                            ->values();
                                @endphp

                                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-approver-stage="{{ $stage->id }}">
                                    <div class="flex items-start gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">
                                            {{ $stage->stage_order }}
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="text-base font-bold text-slate-900">{{ $stage->nama_tahap ?: 'Tahap Approval' }}</h3>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-3" data-approver-slots>
                                        @foreach ($stageApprovers as $item)
                                            @php
                                                $approver = $item['user'];
                                                $approverStatus = $item['status'];
                                                $locked = $item['locked'];
                                            @endphp
                                            <div class="flex items-start gap-2" data-approver-slot>
                                                <div class="min-w-0 flex-1">
                                                    @if ($locked)
                                                        <input type="hidden" name="stage_approvers[{{ $stage->id }}][]" value="{{ $approver->id }}">
                                                        <div class="flex min-h-12 w-full items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600">
                                                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">
                                                                {{ $approver->initials() }}
                                                            </span>
                                                            <span class="min-w-0 flex-1">
                                                                <span class="block truncate font-semibold text-slate-800">{{ $approver->name }}</span>
                                                                <span class="block truncate text-xs text-slate-500">{{ $approver->jabatan ?: $approver->email }}</span>
                                                            </span>
                                                            <span class="shrink-0 rounded-md bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">
                                                                {{ $approvalStatusLabels[$approverStatus] ?? 'Terkunci' }}
                                                            </span>
                                                        </div>
                                                    @else
                                                        <div data-approver-readonly-card @class(['hidden' => ! $assignmentStartsReadonly])>
                                                            <div class="flex min-h-12 w-full items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600">
                                                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-sky-50 text-xs font-bold text-sky-700 ring-1 ring-sky-100">
                                                                    {{ $approver->initials() }}
                                                                </span>
                                                                <span class="min-w-0 flex-1">
                                                                    <span class="block truncate font-semibold text-slate-800">{{ $approver->name }}</span>
                                                                    <span class="block truncate text-xs text-slate-500">{{ $approver->jabatan ?: $approver->email }}</span>
                                                                </span>
                                                                @if ($approverStatus)
                                                                    <span class="shrink-0 rounded-md bg-sky-50 px-2 py-1 text-xs font-bold text-sky-700">
                                                                        {{ $approvalStatusLabels[$approverStatus] ?? $approverStatus }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div data-approver-edit-control @class(['hidden' => $assignmentStartsReadonly])>
                                                            <x-ui.user-search-select
                                                                name="stage_approvers[{{ $stage->id }}][]"
                                                                :users="$assignableUsers"
                                                                :selected-user="$approver"
                                                                placeholder="Cari dan pilih approver"
                                                                required
                                                            />
                                                        </div>
                                                    @endif
                                                </div>
                                                @if (! $locked)
                                                    <div data-approver-edit-control @class(['hidden' => $assignmentStartsReadonly])>
                                                        <x-ui.icon-button
                                                            type="button"
                                                            icon="trash"
                                                            label="Hapus approver"
                                                            variant="ghost"
                                                            data-remove-approver-slot
                                                        />
                                                    </div>
                                                @else
                                                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400" title="Approver terkunci">
                                                        <x-flux.icon name="lock-closed" class="size-5" />
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    @error("stage_approvers.{$stage->id}")
                                        <span class="mt-3 block text-sm font-semibold text-red-500">{{ $message }}</span>
                                    @enderror

                                    @if (! $isStageFullyApproved)
                                        <button type="button" class="mt-4 h-12 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white px-4 text-base font-semibold text-slate-500 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 {{ $assignmentStartsReadonly ? 'hidden' : 'inline-flex' }}" data-add-approver-slot data-approver-edit-control>
                                            <x-flux.icon name="plus" class="size-5" />
                                            Tambah Approver
                                        </button>
                                    @endif
                                </article>
                            @endforeach

                            <div data-approver-edit-control @class(['hidden' => $assignmentStartsReadonly])>
                                <x-ui.action-button type="submit" class="w-full">
                                    Save Approver
                                </x-ui.action-button>
                            </div>
                        </form>
                        @endif
                    </section>
                @endif
            </aside>
        </div>
    </div>

    <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 py-6" data-reject-modal>
        <form method="POST" action="{{ route('documents.approval.reject', $document) }}" class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl">
            @csrf
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Alasan Penolakan</h2>
                    <p class="mt-1 text-sm text-slate-500">Isi catatan agar pengaju memahami bagian yang perlu diperbaiki.</p>
                </div>
                <button type="button" class="text-slate-400 transition hover:text-slate-700" data-reject-modal-close>
                    <x-flux.icon name="x-mark" class="size-5" />
                </button>
            </div>
            <div class="px-6 py-5">
                <x-ui.textarea
                    label="Catatan"
                    name="catatan"
                    rows="5"
                    placeholder="Tulis alasan penolakan..."
                    required
                />
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <x-ui.action-button type="button" variant="secondary" data-reject-modal-close>
                    Batal
                </x-ui.action-button>
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700">
                    Tolak Dokumen
                </button>
            </div>
        </form>
    </div>

    <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 py-6" data-approver-edit-modal>
        <div class="w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Edit Approver?</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Approver yang sudah memberikan respon akan tetap terkunci. Hanya approver yang belum merespon yang bisa diubah.
                    </p>
                </div>
                <button type="button" class="text-slate-400 transition hover:text-slate-700" data-approver-edit-close>
                    <x-flux.icon name="x-mark" class="size-5" />
                </button>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <x-ui.action-button type="button" variant="secondary" data-approver-edit-close>
                    Batal
                </x-ui.action-button>
                <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-700" data-approver-edit-confirm>
                    Ya, Edit
                </button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.querySelector('[data-reject-modal]');

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-reject-modal-open]')) {
                    modal?.classList.remove('hidden');
                    modal?.classList.add('flex');
                }

                if (event.target.closest('[data-reject-modal-close]')) {
                    modal?.classList.add('hidden');
                    modal?.classList.remove('flex');
                }
            });
        })();
    </script>

    <script>
        (() => {
            const form = document.querySelector('[data-approver-assignment-form]');
            const modal = document.querySelector('[data-approver-edit-modal]');

            if (!form) {
                return;
            }

            const showModal = () => {
                modal?.classList.remove('hidden');
                modal?.classList.add('flex');
            };

            const hideModal = () => {
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
            };

            const enableEditMode = () => {
                form.dataset.readonly = 'false';
                form.querySelector('[data-approver-readonly-action]')?.classList.add('hidden');

                form.querySelectorAll('[data-approver-readonly-card]').forEach((element) => {
                    element.classList.add('hidden');
                });

                form.querySelectorAll('[data-approver-edit-control]').forEach((element) => {
                    element.classList.remove('hidden');

                    if (element.matches('[data-add-approver-slot]')) {
                        element.classList.add('inline-flex');
                    }
                });

                hideModal();
            };

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-approver-edit-open]')) {
                    showModal();
                    return;
                }

                if (event.target.closest('[data-approver-edit-close]')) {
                    hideModal();
                    return;
                }

                if (event.target.closest('[data-approver-edit-confirm]')) {
                    enableEditMode();
                }
            });
        })();
    </script>

    <script>
        (() => {
            const editor = document.querySelector('[data-submitted-document-editor]');

            if (editor) {
                const readonly = editor.querySelector('[data-submitted-document-readonly]');
                const readonlyAction = document.querySelector('[data-submitted-document-readonly-action]');
                const form = editor.querySelector('[data-submitted-document-form]');

                document.addEventListener('click', (event) => {
                    if (event.target.closest('[data-submitted-document-edit-open]')) {
                        readonly?.classList.add('hidden');
                        readonlyAction?.classList.add('hidden');
                        form?.classList.remove('hidden');
                        return;
                    }

                    if (event.target.closest('[data-submitted-document-edit-cancel]')) {
                        form?.classList.add('hidden');
                        readonly?.classList.remove('hidden');
                        readonlyAction?.classList.remove('hidden');
                    }
                });
            }

            const renumberSubmittedAttachmentRows = (form) => {
                form?.querySelectorAll('[data-existing-attachment-row] [data-attachment-order-input]').forEach((input, index) => {
                    input.value = index + 1;
                });
                form?.querySelectorAll('[data-attachment-list]').forEach((list) => {
                    window.renumberAttachmentRows?.(list);
                });
            };

            const setSubmittedAttachmentDragState = (attachmentEditor, enabled) => {
                attachmentEditor?.querySelectorAll('[data-existing-attachment-row]').forEach((row) => {
                    const canDrag = enabled && !row.hasAttribute('data-source-attachment-row');

                    row.draggable = canDrag;
                    row.classList.toggle('cursor-grab', canDrag);
                });
            };

            const rowAfterPointer = (list, y) => {
                const rows = [...list.querySelectorAll('[data-existing-attachment-row]:not([data-source-attachment-row]):not([data-attachment-dragging])')];

                return rows.reduce((closest, row) => {
                    const box = row.getBoundingClientRect();
                    const offset = y - box.top - (box.height / 2);

                    if (offset < 0 && offset > closest.offset) {
                        return { offset, row };
                    }

                    return closest;
                }, { offset: Number.NEGATIVE_INFINITY, row: null }).row;
            };

            document.addEventListener('click', (event) => {
                const attachmentEditButton = event.target.closest('[data-attachment-edit-open]');

                if (attachmentEditButton) {
                    const attachmentEditor = attachmentEditButton.closest('[data-attachment-editor]');
                    const isEditing = attachmentEditButton.dataset.editing === 'true';
                    const label = attachmentEditButton.querySelector('[data-attachment-edit-label]');

                    if (isEditing) {
                        attachmentEditButton.dataset.editing = 'false';
                        label.textContent = 'Edit';
                        attachmentEditButton.classList.remove('border-red-200', 'bg-red-50', 'text-red-600', 'hover:border-red-300', 'hover:bg-red-100');
                        attachmentEditButton.classList.add('border-slate-200', 'bg-white', 'text-slate-700', 'hover:bg-slate-50');

                        attachmentEditor?.querySelectorAll('[data-file-edit-control]').forEach((element) => {
                            element.classList.add('hidden');
                            element.classList.remove('flex');
                        });
                        attachmentEditor?.querySelectorAll('[data-attachment-title-input]').forEach((input) => {
                            input.readOnly = true;
                            input.classList.add('border-slate-200', 'bg-slate-50');
                            input.classList.remove('border-slate-300', 'bg-white');
                        });
                        attachmentEditor?.querySelectorAll('[data-file-picker-input]').forEach((input) => {
                            input.value = '';
                        });
                        attachmentEditor?.querySelectorAll('[data-selected-file-name]').forEach((name) => {
                            if (name.dataset.originalFileName) {
                                name.textContent = name.dataset.originalFileName;
                            }
                        });
                        attachmentEditor?.querySelectorAll('[data-attachment-edit-control]').forEach((element) => {
                            element.classList.add('hidden');
                            element.classList.remove('inline-flex');
                            element.classList.remove('grid');
                        });
                        setSubmittedAttachmentDragState(attachmentEditor, false);
                    } else {
                        attachmentEditButton.dataset.editing = 'true';
                        label.textContent = 'Cancel';
                        attachmentEditButton.classList.remove('border-slate-200', 'bg-white', 'text-slate-700', 'hover:bg-slate-50');
                        attachmentEditButton.classList.add('border-red-200', 'bg-red-50', 'text-red-600', 'hover:border-red-300', 'hover:bg-red-100');

                        attachmentEditor?.querySelectorAll('[data-file-edit-control]').forEach((element) => {
                            element.classList.remove('hidden');
                            element.classList.add('flex');
                        });
                        attachmentEditor?.querySelectorAll('[data-attachment-title-input]').forEach((input) => {
                            input.readOnly = false;
                            input.classList.remove('border-slate-200', 'bg-slate-50');
                            input.classList.add('border-slate-300', 'bg-white');
                        });
                        attachmentEditor?.querySelectorAll('[data-attachment-edit-control]').forEach((element) => {
                            element.classList.remove('hidden');

                            if (element.matches('button')) {
                                element.classList.add('inline-flex');
                            } else if (element.matches('label')) {
                                element.classList.add('grid');
                            }
                        });
                        setSubmittedAttachmentDragState(attachmentEditor, true);
                    }

                    return;
                }

                const editButton = event.target.closest('[data-file-edit-open]');

                if (editButton) {
                    const container = editButton.closest('form, [data-existing-attachment-row]');
                    const attachmentForm = editButton.closest('[data-submitted-attachment-form]');

                    container?.querySelector('[data-file-readonly-action]')?.classList.add('hidden');
                    container?.querySelectorAll('[data-file-edit-control]').forEach((element) => {
                        element.classList.remove('hidden');
                        element.classList.add('flex');
                    });

                    const titleInput = container?.querySelector('[data-attachment-title-input]');

                    if (titleInput) {
                        titleInput.readOnly = false;
                        titleInput.classList.remove('border-slate-200', 'bg-slate-50');
                        titleInput.classList.add('border-slate-300', 'bg-white');
                    }

                    attachmentForm?.querySelectorAll('[data-attachment-edit-control]').forEach((element) => {
                        element.classList.remove('hidden');

                        if (element.matches('button')) {
                            element.classList.add('inline-flex');
                        } else if (element.matches('label')) {
                            element.classList.add('grid');
                        }
                    });

                    return;
                }

                const cancelButton = event.target.closest('[data-file-edit-cancel]');

                if (cancelButton) {
                    const container = cancelButton.closest('form, [data-existing-attachment-row]');
                    const attachmentForm = cancelButton.closest('[data-submitted-attachment-form]');
                    const attachmentEditor = cancelButton.closest('[data-attachment-editor]');
                    const readonlyAction = container?.querySelector('[data-file-readonly-action]');
                    const editControls = container?.querySelectorAll('[data-file-edit-control]');
                    const titleInput = container?.querySelector('[data-attachment-title-input]');

                    editControls?.forEach((element) => {
                        element.classList.add('hidden');
                        element.classList.remove('flex');
                    });
                    readonlyAction?.classList.remove('hidden');

                    container?.querySelectorAll('[data-file-picker-input]').forEach((fileInput) => {
                        fileInput.value = '';
                    });

                    container?.querySelectorAll('[data-selected-file-name]').forEach((name) => {
                        if (name.dataset.originalFileName) {
                            name.textContent = name.dataset.originalFileName;
                        }
                    });

                    if (titleInput) {
                        titleInput.readOnly = true;
                        titleInput.classList.add('border-slate-200', 'bg-slate-50');
                        titleInput.classList.remove('border-slate-300', 'bg-white');
                    }

                    const hasOpenAttachmentRows = Array.from(attachmentForm?.querySelectorAll('[data-existing-attachment-row]') ?? [])
                        .some((row) => !row.querySelector('[data-file-edit-control]')?.classList.contains('hidden'));

                    if (attachmentForm && !hasOpenAttachmentRows) {
                        attachmentForm.querySelectorAll('[data-attachment-edit-control]').forEach((element) => {
                            element.classList.add('hidden');
                            element.classList.remove('inline-flex');
                            element.classList.remove('grid');
                        });
                    }

                    return;
                }

                const removeSubmittedAttachmentButton = event.target.closest('[data-submitted-attachment-remove]');

                if (removeSubmittedAttachmentButton) {
                    const row = removeSubmittedAttachmentButton.closest('[data-existing-attachment-row]');
                    const form = removeSubmittedAttachmentButton.closest('[data-submitted-attachment-form]');
                    const fileId = row?.querySelector('[data-existing-attachment-id]')?.value;

                    if (fileId && form) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'remove_existing_files[]';
                        input.value = fileId;
                        form.append(input);
                    }

                    row?.remove();
                    renumberSubmittedAttachmentRows(form);

                    return;
                }

                const button = event.target.closest('[data-file-picker-button]');

                if (!button) {
                    return;
                }

                const container = button.closest('[data-file-edit-item], [data-existing-attachment-row], form');
                container?.querySelector('[data-file-picker-input]')?.click();
            });

            document.addEventListener('dragstart', (event) => {
                const row = event.target.closest('[data-existing-attachment-row]');
                const handle = event.target.closest('[data-attachment-drag-handle]');

                if (!row) {
                    return;
                }

                if (row.hasAttribute('data-source-attachment-row') || !handle || row.draggable !== true) {
                    event.preventDefault();

                    return;
                }

                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', '');
                row.dataset.attachmentDragging = 'true';
                row.classList.add('opacity-60', 'ring-2', 'ring-sky-100');
            });

            document.addEventListener('dragover', (event) => {
                const list = event.target.closest('[data-submitted-attachment-items]');
                const draggingRow = document.querySelector('[data-attachment-dragging]');

                if (!list || !draggingRow) {
                    return;
                }

                event.preventDefault();
                const afterRow = rowAfterPointer(list, event.clientY);

                if (afterRow === null) {
                    list.appendChild(draggingRow);
                } else {
                    list.insertBefore(draggingRow, afterRow);
                }
            });

            document.addEventListener('dragend', () => {
                const row = document.querySelector('[data-attachment-dragging]');

                if (!row) {
                    return;
                }

                const form = row.closest('[data-submitted-attachment-form]');

                row.classList.remove('opacity-60', 'ring-2', 'ring-sky-100');
                delete row.dataset.attachmentDragging;
                renumberSubmittedAttachmentRows(form);
            });

            document.addEventListener('change', (event) => {
                const input = event.target.closest('[data-file-picker-input]');
                const file = input?.files?.[0];

                if (!input || !file) {
                    return;
                }

                const container = input.closest('[data-file-edit-item], [data-existing-attachment-row], form');
                const name = container?.querySelector('[data-selected-file-name]');

                if (name) {
                    name.textContent = file.name;
                }
            });

            document.addEventListener('change', (event) => {
                const checkbox = event.target.closest('[data-source-attachment-include]');

                if (!checkbox) {
                    return;
                }

                const state = checkbox.closest('label')?.querySelector('[data-source-attachment-state]');

                if (state) {
                    state.textContent = checkbox.checked ? 'Dicantumkan' : 'Tidak Dicantumkan';
                    state.classList.toggle('bg-emerald-50', checkbox.checked);
                    state.classList.toggle('text-emerald-700', checkbox.checked);
                    state.classList.toggle('ring-emerald-100', checkbox.checked);
                    state.classList.toggle('bg-red-50', !checkbox.checked);
                    state.classList.toggle('text-red-600', !checkbox.checked);
                    state.classList.toggle('ring-red-100', !checkbox.checked);
                }
            });
        })();
    </script>

    <template data-approver-slot-template>
        <div class="flex items-start gap-2" data-approver-slot>
            <div class="min-w-0 flex-1">
                <x-ui.user-search-select
                    name="__NAME__"
                    :users="$assignableUsers"
                    placeholder="Cari dan pilih approver"
                    required
                />
            </div>
            <x-ui.icon-button
                type="button"
                icon="trash"
                label="Hapus approver"
                variant="ghost"
                data-remove-approver-slot
            />
        </div>
    </template>

    <script>
        (() => {
            if (window.approverSlotManagerReady) {
                return;
            }

            window.approverSlotManagerReady = true;

            document.addEventListener('click', (event) => {
                const addButton = event.target.closest('[data-add-approver-slot]');

                if (addButton) {
                    const template = document.querySelector('[data-approver-slot-template]');
                    const stage = addButton.closest('[data-approver-stage]');
                    const list = stage?.querySelector('[data-approver-slots]');

                    if (!stage || !list || !template) {
                        return;
                    }

                    const slot = template.innerHTML.replaceAll('__NAME__', `stage_approvers[${stage.dataset.approverStage}][]`);
                    list.insertAdjacentHTML('beforeend', slot);
                    return;
                }

                const removeButton = event.target.closest('[data-remove-approver-slot]');

                if (removeButton) {
                    removeButton.closest('[data-approver-slot]')?.remove();
                }
            });
        })();
    </script>
</x-layouts.app>
