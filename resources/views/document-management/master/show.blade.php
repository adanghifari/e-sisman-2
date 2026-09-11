<x-documents.detail-page
    title="Detail Dokumen Master"
    heading="Detail Dokumen Master"
    index-route="documents.master"
    index-label="Dokumen Master"
    :document="$document"
    :master-display-number="$masterDisplayNumber"
    :revision-request-display-number="$revisionRequestDisplayNumber"
    :stamp-label="$document->status?->nama_status === \App\Models\StatusDocument::OBSOLETE ? 'Obsolete' : 'Master'"
    :stamp-tone="$document->status?->nama_status === \App\Models\StatusDocument::OBSOLETE ? 'red' : 'sky'"
    :file-route-prefix="$document->origin === \App\Models\Document::ORIGIN_WORKFLOW ? 'documents.master' : 'documents.existing.imports'"
    :approval-flow-stages="$approvalFlowStages"
    :content-files="$contentFiles"
    :attachment-files="$attachmentFiles"
    :generated-printout="$generatedPrintout"
    :show-generated-printout="$document->origin === \App\Models\Document::ORIGIN_WORKFLOW"
    :can-preview-generated-printout="$canPreviewGeneratedPrintout"
    :download-printout-url="$downloadPrintoutUrl ?? null"
    :show-source-files="$document->origin !== \App\Models\Document::ORIGIN_WORKFLOW"
    :document-history="$documentHistory"
    :related-obsolete-documents="$relatedObsoleteDocuments ?? collect()"
>
    <x-slot name="actions">
        @if (($canEdit ?? false) || $canRequestRevision || $canRequestObsolete || ($canDeleteImportedExisting ?? false))
            <div class="border-t border-dashed border-slate-200 px-6 py-5">
                <div class="grid gap-3">
                    @if ($canEdit ?? false)
                        <a
                            href="{{ route('documents.master.imports.edit', $document) }}"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700"
                        >
                            <x-flux.icon name="pencil-square" class="size-4" />
                            Edit Metadata Dokumen
                        </a>
                    @endif
                    @if ($canRequestRevision)
                        @if ($hasActiveRevisionRequest ?? false)
                            <button
                                type="button"
                                class="inline-flex h-11 cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-100 px-4 text-sm font-semibold text-slate-400 shadow-sm"
                                disabled
                            >
                                <x-flux.icon name="clock" class="size-4" />
                                Revisi dalam Pengajuan
                            </button>
                        @else
                            <a
                                href="{{ route('documents.create.level', $document->origin === \App\Models\Document::ORIGIN_WORKFLOW ? ['level-4', 'revised_from' => $document->id] : ['level-4', 'imported_source' => $document->id]) }}"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700"
                            >
                                <x-flux.icon name="arrow-path" class="size-4" />
                                Ajukan Revisi
                            </a>
                        @endif
                    @endif
                    @if ($canRequestObsolete)
                        <button type="button" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-600 shadow-sm transition hover:border-red-300 hover:bg-red-50" data-obsolete-modal-open>
                            <x-flux.icon name="archive-box-x-mark" class="size-4" />
                            Obsolete
                        </button>
                    @endif
                    @if ($canDeleteImportedExisting ?? false)
                        <form
                            method="POST"
                            action="{{ route('documents.existing.imports.destroy', $document) }}"
                            onsubmit="return confirm('Hapus dokumen imported ini dari sistem?')"
                        >
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 text-sm font-semibold text-red-600 shadow-sm transition hover:border-red-300 hover:bg-red-50"
                            >
                                <x-flux.icon name="trash" class="size-4" />
                                Hapus Import
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </x-slot>
    <x-slot name="modals">
        @if ($canRequestObsolete)
            <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 py-6" data-obsolete-modal>
                <form method="POST" action="{{ ($canDirectObsoleteImported ?? false) ? route('documents.master.imported.obsolete', $document) : route('documents.master.obsolete', $document) }}" class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl">
                    @csrf
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ ($canDirectObsoleteImported ?? false) ? 'Obsolete Dokumen Master' : 'Pengajuan Obsolete' }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ ($canDirectObsoleteImported ?? false) ? 'Isi alasan obsolete untuk menonaktifkan dokumen master ini.' : 'Isi alasan obsolete sebelum dokumen masuk ke proses approval.' }}</p>
                        </div>
                        <button type="button" class="text-slate-400 transition hover:text-slate-700" data-obsolete-modal-close>
                            <x-flux.icon name="x-mark" class="size-5" />
                        </button>
                    </div>
                    <div class="px-6 py-5">
                        <x-ui.textarea
                            label="Catatan / Alasan Obsolete"
                            name="catatan_obsolete"
                            rows="5"
                            placeholder="Tulis alasan obsolete..."
                            required
                        />
                        @error('catatan_obsolete')
                            <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                        <x-ui.action-button type="button" variant="secondary" data-obsolete-modal-close>
                            Batal
                        </x-ui.action-button>
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700">
                            {{ ($canDirectObsoleteImported ?? false) ? 'Obsolete Dokumen' : 'Pengajuan Obsolete' }}
                        </button>
                    </div>
                </form>
            </div>

            <script>
                (() => {
                    const modal = document.querySelector('[data-obsolete-modal]');

                    document.addEventListener('click', (event) => {
                        if (event.target.closest('[data-obsolete-modal-open]')) {
                            modal?.classList.remove('hidden');
                            modal?.classList.add('flex');
                        }

                        if (event.target.closest('[data-obsolete-modal-close]')) {
                            modal?.classList.add('hidden');
                            modal?.classList.remove('flex');
                        }
                    });
                })();
            </script>
        @endif
    </x-slot>
</x-documents.detail-page>
