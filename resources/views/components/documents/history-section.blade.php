@props([
    'documentHistory' => collect(),
    'title' => 'Riwayat Dokumen',
    'notice' => null,
])

<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-5">
        <h3 class="text-sm font-bold text-slate-900">{{ $title }}</h3>
    </div>
    <div class="max-h-[30rem] space-y-2 overflow-y-auto px-6 py-5 app-scrollbar">
        @if (filled($notice))
            <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-medium leading-6 text-slate-600">
                {{ $notice }}
            </p>
        @else
            @forelse ($documentHistory as $history)
                @php
                    $historyTimestamp = $history['timestamp'];
                @endphp
                <div class="rounded-lg bg-slate-50 px-3 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">{{ $history['description'] }}</p>
                            <p class="mt-1 text-xs font-medium text-slate-500">
                                {{ $history['document_number'] }} - Revisi {{ $history['revision'] }}
                            </p>
                            <p class="mt-1 text-xs font-medium text-slate-500">
                                Transaksi #{{ $history['document_id'] }}
                            </p>
                            <p class="mt-1 text-xs font-medium text-slate-500">
                                {{ $historyTimestamp ? $historyTimestamp->translatedFormat('d M Y H:i:s') : 'Belum tercatat' }}
                            </p>
                            @if (filled($history['note'] ?? null))
                                <p class="mt-2 rounded-md bg-white px-2 py-1 text-xs text-slate-600">
                                    {{ $history['note'] }}
                                </p>
                            @endif
                        </div>
                        <x-ui.status-badge
                            :label="$history['label']"
                            :tone="$historyTimestamp ? $history['tone'] : 'slate'"
                            class="shrink-0"
                        />
                    </div>
                </div>
            @empty
                <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm font-semibold text-slate-500">
                    Belum ada riwayat dokumen.
                </p>
            @endforelse
        @endif
    </div>
</section>
