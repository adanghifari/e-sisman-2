@props([
    'name',
    'documents',
    'placeholder' => 'Pilih dokumen',
    'value' => null,
    'emptyLabel' => 'Dokumen tidak ditemukan.',
    'filterByContext' => false,
])

@php
    $selectedDocument = collect($documents)->firstWhere('value', (string) $value);
@endphp

<div
    {{ $attributes->class(['relative']) }}
    data-document-search-select
    @if ($filterByContext) data-filter-by-context="true" @endif
    data-placeholder="{{ $placeholder }}"
>
    <input
        type="hidden"
        name="{{ $name }}"
        value="{{ $selectedDocument['value'] ?? '' }}"
        data-document-search-value
    >

    <button
        type="button"
        class="flex h-12 w-full items-center gap-3 rounded-lg border border-slate-300 bg-white px-3 text-left text-sm font-medium text-slate-600 outline-none transition hover:border-sky-300 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
        data-document-search-trigger
        aria-expanded="false"
    >
        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-700 ring-1 ring-sky-100">
            <x-flux.icon name="document-text" class="size-4" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate font-semibold" data-document-search-name>{{ $selectedDocument['label'] ?? $placeholder }}</span>
            <span @class(['truncate text-xs text-slate-500', 'hidden' => ! $selectedDocument]) data-document-search-meta>{{ $selectedDocument['meta'] ?? '' }}</span>
        </span>
        <x-flux.icon name="chevron-down" class="size-5 shrink-0 text-slate-400" />
    </button>

    <div class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg" data-document-search-panel>
        <div class="border-b border-slate-100 p-2">
            <input
                type="search"
                placeholder="Cari nomor atau nama dokumen"
                class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-600 outline-none transition placeholder:text-slate-400 focus:border-sky-300 focus:bg-white focus:ring-2 focus:ring-sky-100"
                data-document-search-input
            >
        </div>

        <div class="max-h-80 overflow-y-auto py-1 app-scrollbar" data-document-search-options>
            @foreach ($documents as $document)
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                    data-document-search-option
                    data-value="{{ $document['value'] }}"
                    data-name="{{ $document['label'] }}"
                    data-meta="{{ $document['meta'] ?? '' }}"
                    data-is-master="{{ ! empty($document['is_master']) ? 'true' : 'false' }}"
                    data-document-level-id="{{ $document['document_level_id'] }}"
                    data-business-process-id="{{ $document['business_process_id'] }}"
                    data-business-function-id="{{ $document['business_function_id'] }}"
                    data-search="{{ \Illuminate\Support\Str::lower(($document['label'] ?? '').' '.($document['meta'] ?? '')) }}"
                >
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                        <x-flux.icon name="document-text" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold text-slate-900">{{ $document['label'] }}</span>
                        <span class="mt-0.5 block truncate text-xs font-medium text-slate-500">{{ $document['meta'] ?? '' }}</span>
                    </span>
                </button>
            @endforeach

            <div class="hidden px-3 py-4 text-center text-sm font-medium text-slate-500" data-document-search-empty>
                {{ $emptyLabel }}
            </div>
        </div>
    </div>
</div>
