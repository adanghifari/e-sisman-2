<x-documents.detail-page
    title="Detail Dokumen Obsolete"
    heading="Detail Dokumen Obsolete"
    index-route="documents.obsolete"
    index-label="Dokumen Obsolete"
    :document="$document"
    :master-display-number="$masterDisplayNumber"
    :revision-request-display-number="$revisionRequestDisplayNumber"
    stamp-label="Obsolete"
    stamp-tone="red"
    file-route-prefix="documents.obsolete"
    :approval-flow-stages="$approvalFlowStages"
    :content-files="$contentFiles"
    :attachment-files="$attachmentFiles"
    :generated-printout="$generatedPrintout"
    :show-generated-printout="$document->origin === \App\Models\Document::ORIGIN_WORKFLOW"
    :can-preview-generated-printout="$canPreviewGeneratedPrintout"
    :download-printout-url="$downloadPrintoutUrl ?? null"
    :show-source-files="$document->origin !== \App\Models\Document::ORIGIN_WORKFLOW"
    :document-history="$documentHistory"
>
    <x-slot name="actions">
        @if ($canRestoreMaster)
            <div class="border-t border-dashed border-slate-200 px-6 py-5">
                <form method="POST" action="{{ route('documents.obsolete.restore', $document) }}">
                    @csrf
                    <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                        <x-flux.icon name="arrow-path" class="size-4" />
                        Jadikan Master
                    </button>
                </form>
            </div>
        @endif
    </x-slot>
</x-documents.detail-page>
