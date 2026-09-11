<x-layouts.app :title="__('Detail Arsip Dokumen Existing')">
    <div class="space-y-6">
        <x-ui.page-header
            title="Detail Arsip Dokumen Existing"
            :description="$document->nama_dokumen"
        />

        @if ($canDeleteImportedExisting)
            <div class="flex justify-end">
                <form
                    method="POST"
                    action="{{ route('documents.existing.imports.destroy', $document) }}"
                    onsubmit="return confirm('Hapus dokumen imported ini dari sistem?')"
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                    >
                        <x-flux.icon name="trash" class="size-4" />
                        Hapus
                    </button>
                </form>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <div class="space-y-6">
                <x-ui.panel title="Informasi Dokumen">
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Dokumen</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->nama_dokumen }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Ketentuan</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $ruleOptions[$document->obsolete_rule_type] ?? $document->obsolete_rule_type }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor Dokumen</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->nomor_dokumen ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor Revisi</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->nomor_revisi ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Terbit</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->tanggal_terbit?->format('d/m/Y') ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Obsolete</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->tanggal_obsolete?->format('d/m/Y') ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dok Level</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->documentLevel?->nama_dokumen ?: 'Tidak dipetakan' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Dokumen</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->documentType?->nama_types ?: 'Tidak dipetakan' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Proses Bisnis</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->businessProcess?->nama_proses_bisnis ?: 'Tidak dipetakan' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Proses Fungsi</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $document->businessFunction?->nama_proses_fungsi ?: 'Tidak dipetakan' }}</dd>
                        </div>
                    </dl>

                    @if (filled($document->catatan))
                        <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan Dokumen</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ $document->catatan }}</p>
                        </div>
                    @endif
                </x-ui.panel>

                <x-ui.panel title="File Dokumen" :padded="false">
                    <div class="divide-y divide-slate-100">
                        @forelse ($document->files as $file)
                            <div class="flex items-center justify-between gap-4 px-6 py-4">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-800">{{ $file->original_file_name }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $file->type_file === \App\Models\DocumentFile::TYPE_IMPORTED_DOCUMENT ? 'File Dokumen' : 'Lampiran' }}</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    @if (\Illuminate\Support\Str::of($file->original_file_name)->lower()->endsWith('.pdf'))
                                        <x-ui.icon-button :href="route('documents.existing.imports.files.preview', [$document, $file])" icon="eye" label="Lihat Dokumen" size="sm" />
                                    @endif
                                    <x-ui.icon-button :href="route('documents.existing.imports.files.show', [$document, $file])" icon="arrow-down-tray" label="Buka file" size="sm" />
                                </div>
                            </div>
                        @empty
                            <p class="px-6 py-8 text-center text-sm font-medium text-slate-500">Belum ada file.</p>
                        @endforelse
                    </div>
                </x-ui.panel>
            </div>

            <aside class="space-y-6">
                <x-ui.panel title="Relasi Keluar">
                    <div class="space-y-3">
                        @forelse ($document->outgoingRelations as $relation)
                            @php
                                $target = $relation->targetDocument;
                                $targetNumber = $target?->nomor_dokumen ?: '-';
                                $targetName = $target?->nama_dokumen ?: '-';
                            @endphp
                            <div class="rounded-lg border border-slate-200 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $relationTypeOptions[$relation->relation_type] ?? $relation->relation_type }}</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ $targetName }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $targetNumber }}</p>
                                @if (filled($relation->keterangan))
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $relation->keterangan }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm font-medium text-slate-500">Belum ada relasi keluar.</p>
                        @endforelse
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Relasi Masuk">
                    <div class="space-y-3">
                        @forelse ($document->incomingRelations as $relation)
                            @php($sourceDocument = $relation->sourceDocument)
                            <div class="rounded-lg border border-slate-200 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ $relation->relation_type === \App\Models\DocumentRelation::SUPERSEDED_BY ? 'Menggantikan' : ($relationTypeOptions[$relation->relation_type] ?? $relation->relation_type) }}
                                </p>
                                <p class="mt-1 font-semibold text-slate-800">{{ $sourceDocument?->nama_dokumen ?: '-' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $sourceDocument?->nomor_dokumen ?: '-' }}</p>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm font-medium text-slate-500">Belum ada relasi masuk.</p>
                        @endforelse
                    </div>
                </x-ui.panel>
            </aside>
        </div>
    </div>
</x-layouts.app>

