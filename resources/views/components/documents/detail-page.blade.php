@props([
    'title',
    'heading',
    'indexRoute',
    'indexLabel',
    'document',
    'masterDisplayNumber',
    'revisionRequestDisplayNumber' => null,
    'stampLabel',
    'stampTone' => 'sky',
    'fileRoutePrefix',
    'approvalFlowStages' => collect(),
    'contentFiles' => collect(),
    'attachmentFiles' => collect(),
    'generatedPrintout' => null,
    'showGeneratedPrintout' => false,
    'canPreviewGeneratedPrintout' => false,
    'downloadPrintoutUrl' => null,
    'showSourceFiles' => true,
    'showFileOpenButton' => true,
    'showAttachmentsSection' => true,
    'documentHistory' => collect(),
    'relatedObsoleteDocuments' => collect(),
    'showOwnerSection' => true,
    'showApprovalHistory' => true,
    'originNotice' => null,
    'actionsNotice' => null,
    'documentHistoryTitle' => 'Riwayat Dokumen',
    'documentHistoryNotice' => null,
    'documentNameBadge' => null,
    'documentNameBadgeTone' => 'sky',
])

@php
    $levelKey = $document->documentLevel?->kode ?? 'level-3';
    $ownerLabel = $levelKey === 'level-1' ? 'Penyusun Dokumen' : 'Penyusun Pemilik Proses';
    $publishedAt = $document->tanggal_terbit ?? $document->approved_at;
    $levelTitle = trim(($document->documentLevel?->nama_level ?? '').' : '.\Illuminate\Support\Str::after($document->documentLevel?->nama_dokumen ?? '', ': '), ' :');
    $processLabel = collect([
        $document->businessProcess?->nama_proses_bisnis,
        $document->businessFunction?->nama_proses_fungsi,
    ])->filter()->implode(' / ');
    $fileTypeLabels = [
                        'filled_template' => 'Template Dokumen',
                        'imported_document' => 'Dokumen Import',
                        'existing_document' => 'Dokumen Existing',
                        'revision_content' => 'Dokumen Revisi',
                        'revision_content_word' => 'Dokumen Revisi Word',
                        'revision_form' => 'Lembar Revisi',
                        'revision_form_word' => 'Lembar Revisi Word',
                        'attachment' => 'Lampiran',
    ];
    $documentFiles = $document->files ?? collect();
    $finalArtifacts = $document->finalArtifacts ?? collect();
    $printoutVersion = collect([
        $document->updated_at?->timestamp,
        $documentFiles->max(fn ($file) => $file->updated_at?->timestamp),
        $documentFiles->max('id'),
        $finalArtifacts->max('id'),
    ])->filter()->implode('-');
    $readonlyInput = 'h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-4 text-base font-semibold text-slate-600 outline-none';
@endphp

<x-layouts.app :title="__($title)">
    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700">Home</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route($indexRoute) }}" class="transition hover:text-sky-700">{{ $indexLabel }}</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">{{ $masterDisplayNumber }}</span>
        </nav>

        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-normal text-slate-950 md:text-4xl">{{ $heading }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <p class="text-base font-medium text-slate-500">{{ $document->nama_dokumen }}</p>
                    @if (filled($documentNameBadge))
                        <x-ui.status-badge :label="$documentNameBadge" :tone="$documentNameBadgeTone" />
                    @endif
                </div>
            </div>

            <x-ui.status-badge :label="$stampLabel" :tone="$stampTone" class="mt-1" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_440px]">
            <div class="space-y-6">
                <x-documents.form-section title="Informasi Dokumen">
                    <dl class="divide-y divide-slate-100 px-6 py-4">
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Nama Dokumen</dt>
                            <dd class="text-sm font-bold uppercase leading-6 text-slate-900">{{ $document->nama_dokumen }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Level Dokumen</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $levelTitle ?: '-' }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Nomor Dokumen</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $masterDisplayNumber }}</dd>
                        </div>
                        @if ($document->nomor_lembar_revisi)
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Nomor Lembar Revisi</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $document->nomor_lembar_revisi }}</dd>
                            </div>
                        @endif
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Revisi</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->formatted_revision }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Proses / Fungsi</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $processLabel ?: '-' }}</dd>
                        </div>
                        <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">Department Terkait</dt>
                            <dd class="text-sm font-bold text-slate-900">{{ $document->departments->map(fn ($department) => ($department->kode_department ? $department->kode_department.' - ' : '').$department->nama_department)->implode(', ') ?: '-' }}</dd>
                        </div>
                        @if ($document->origin !== \App\Models\Document::ORIGIN_WORKFLOW && filled($document->catatan))
                            <div class="grid gap-1 py-3 md:grid-cols-[220px_minmax(0,1fr)]">
                                <dt class="text-sm font-semibold text-slate-500">Catatan Import</dt>
                                <dd class="whitespace-pre-line text-sm font-bold leading-6 text-slate-900">{{ $document->catatan }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if (filled($originNotice))
                        <div class="mx-6 mb-6 rounded-lg border border-sky-100 bg-sky-50 px-4 py-3 text-sm font-medium leading-6 text-sky-800">
                            {{ $originNotice }}
                        </div>
                    @endif
                </x-documents.form-section>

                @if ($showOwnerSection)
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-900">{{ $ownerLabel }}</h2>
                        </div>

                        <div class="grid gap-4 px-6 py-6 md:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pengisi Form</span>
                                <div class="mt-2 flex items-center gap-2.5">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">
                                        {{ $document->creator?->initials() ?? '-' }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ $document->creator?->name ?? '-' }}</span>
                                        <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">{{ $document->creator?->jabatan ?: $document->creator?->email }}</span>
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
                        </div>
                    </section>
                @endif

                @if ($showGeneratedPrintout)
                    <x-documents.form-section title="Printout PDF Final" icon="document-check">
                        <div class="space-y-4 px-6 py-6">
                            @if ($canPreviewGeneratedPrintout)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <div class="border-b border-slate-200 bg-white px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">Printout PDF Final</p>
                                        </div>
                                    </div>

                                    <x-documents.lazy-pdf-preview :src="route($fileRoutePrefix.'.generated.show', [$document, 'v' => $printoutVersion]).'#toolbar=0&view=FitH&navpanes=0'" />
                                </section>
                            @else
                                <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                    Printout PDF final belum tersedia karena file sumber dokumen belum lengkap.
                                </p>
                            @endif
                        </div>
                    </x-documents.form-section>
                @endif

                @if ($showSourceFiles)
                    <x-documents.form-section title="Isi Dokumen" icon="document-text">
                        <div class="space-y-4 px-6 py-6">
                            @forelse ($contentFiles as $file)
                                <section class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $file->original_file_name }}</p>
                                            <p class="text-xs font-medium text-slate-500">{{ $fileTypeLabels[$file->type_file] ?? strtoupper(str_replace('_', ' ', $file->type_file)) }}</p>
                                        </div>
                                        @if ($showFileOpenButton)
                                            <a href="{{ route($fileRoutePrefix.'.files.show', [$document, $file]) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                                Buka
                                            </a>
                                        @endif
                                    </div>

                                    @if (\Illuminate\Support\Str::of($file->original_file_name)->lower()->endsWith('.pdf'))
                                        <x-documents.lazy-pdf-preview :src="route($fileRoutePrefix.'.files.preview', [$document, $file]).'#toolbar=0&view=FitH&navpanes=0'" />
                                    @else
                                        <div class="px-4 py-8 text-center text-sm font-medium text-slate-500">
                                            Preview hanya tersedia untuk PDF.
                                        </div>
                                    @endif
                                </section>
                            @empty
                                <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                    Belum ada file isi dokumen.
                                </p>
                            @endforelse
                        </div>
                    </x-documents.form-section>

                    @if ($showAttachmentsSection)
                        <x-documents.form-section title="Lampiran" icon="paper-clip">
                            <div class="space-y-3 px-6 py-6">
                                @forelse ($attachmentFiles as $file)
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ $file->attachment_title ?: $file->original_file_name }}</p>
                                            <p class="text-xs font-medium text-slate-500">{{ $file->original_file_name }} - {{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB</p>
                                        </div>
                                        <a href="{{ route($fileRoutePrefix.'.files.show', [$document, $file]) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            Buka
                                        </a>
                                    </div>
                                @empty
                                    <p class="rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-medium text-slate-500">
                                        Tidak ada lampiran.
                                    </p>
                                @endforelse
                            </div>
                        </x-documents.form-section>
                    @endif
                @endif

                @if ($relatedObsoleteDocuments->isNotEmpty())
                    <x-documents.form-section title="Dokumen Obsolete Terkait" icon="archive-box-x-mark">
                        <div class="space-y-3 px-6 py-6">
                            @foreach ($relatedObsoleteDocuments as $obsolete)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold uppercase tracking-wide text-slate-900">{{ $obsolete->nama_dokumen }}</p>
                                        <p class="mt-1 text-xs font-medium text-slate-500">
                                            {{ $obsolete->nomor_dokumen }} - Revisi {{ $obsolete->nomor_revisi }}
                                        </p>
                                    </div>
                                    <a href="{{ $obsolete->detail_url }}" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Lihat
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </x-documents.form-section>
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
                                <x-flux.icon name="document-check" class="size-6 text-slate-700" />
                                <span>Stamp</span>
                                <x-ui.status-badge :label="$stampLabel" :tone="$stampTone" class="ml-auto" />
                            </div>
                            <div class="flex items-center gap-3">
                                <x-flux.icon name="calendar-days" class="size-6 text-slate-700" />
                                <span>Tanggal Pengajuan</span>
                                <span class="ml-auto text-slate-500">{{ $document->submitted_at?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-flux.icon name="calendar" class="size-6 text-slate-700" />
                                <span>Tanggal Terbit</span>
                                <span class="ml-auto text-slate-500">{{ $publishedAt?->translatedFormat('d M Y') ?? '-' }}</span>
                            </div>
                        </div>
                    </div>

                    @if (($showGeneratedPrintout && $canPreviewGeneratedPrintout) || filled($downloadPrintoutUrl))
                        <div class="border-t border-dashed border-slate-200 px-6 py-5">
                            <a
                                href="{{ $downloadPrintoutUrl ?: route($fileRoutePrefix.'.generated.show', [$document, 'download' => 1]) }}"
                                target="_blank"
                                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
                            >
                                <x-flux.icon name="arrow-down-tray" class="size-4" />
                                Download Printout PDF
                            </a>
                        </div>
                    @endif

                    {{ $actions ?? '' }}

                    @if (filled($actionsNotice))
                        <div class="border-t border-dashed border-slate-200 px-6 py-5">
                            <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium leading-6 text-slate-600">
                                {{ $actionsNotice }}
                            </p>
                        </div>
                    @endif
                </section>

                @if ($showApprovalHistory)
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
                        @endphp
                        <div class="space-y-2 px-6 py-5">
                            @forelse ($approvalHistory as $approval)
                                @php
                                    $approvalStatusCode = $approval->status?->kode_status;
                                    $approvalTimestamp = $approval->responded_at;
                                    $approvalStatusTone = match ($approvalStatusCode) {
                                        \App\Models\ApprovalStatus::PENDING => 'amber',
                                        \App\Models\ApprovalStatus::WAITING => 'sky',
                                        \App\Models\ApprovalStatus::APPROVED => 'emerald',
                                        \App\Models\ApprovalStatus::REJECTED => 'red',
                                        default => 'slate',
                                    };
                                @endphp
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="truncate text-sm font-semibold text-slate-800">{{ $approval->approver?->name ?? '-' }}</p>
                                        <x-ui.status-badge :label="$approval->status?->nama_status ?? '-'" :tone="$approvalStatusTone" />
                                    </div>
                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $approval->stages ?: 'Approval' }}</p>
                                    @if ($approvalTimestamp)
                                        <p class="mt-1 text-xs font-medium text-slate-500">
                                            Diproses pada {{ $approvalTimestamp->translatedFormat('d M Y H:i:s') }}
                                        </p>
                                    @endif
                                    @if ($approval->catatan)
                                        <p class="mt-2 rounded-md bg-white px-2 py-1 text-xs text-slate-600">{{ $approval->catatan }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm font-semibold text-slate-500">
                                    Belum ada riwayat approver.
                                </p>
                            @endforelse
                        </div>
                    </section>
                @endif

                <x-documents.history-section
                    :document-history="$documentHistory"
                    :title="$documentHistoryTitle"
                    :notice="$documentHistoryNotice"
                />
            </aside>
        </div>
    </div>

    {{ $modals ?? '' }}
</x-layouts.app>
