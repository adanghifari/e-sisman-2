<x-layouts.app :title="__('Inbox Approval')">
    <div class="space-y-6">
        <x-ui.page-header
            title="Inbox Approval"
            description="Pantau dokumen yang perlu Anda proses dan riwayat approval yang sudah Anda lakukan."
        />

        <x-ui.filter-bar :action="route('documents.inbox')">
            <x-slot name="tabs">
                @foreach ($tabs as $key => $tab)
                    @php
                        $active = $activeTab === $key;
                    @endphp

                    <a
                        href="{{ route('documents.inbox', ['tab' => $key]) }}"
                        class="inline-flex min-h-10 items-center gap-2 rounded-md px-3 text-sm font-semibold transition {{ $active ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                    >
                        <span>{{ $tab['label'] }}</span>
                        <span class="rounded-md px-2 py-0.5 text-xs {{ $active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">
                            {{ $tab['count'] }}
                        </span>
                    </a>
                @endforeach
            </x-slot>

            <input type="hidden" name="tab" value="{{ $activeTab }}">

            <x-ui.input
                label="Cari"
                name="search"
                :value="$filters['search']"
                placeholder="Cari nomor, dokumen, pengaju, tahap..."
            />

            <x-ui.select label="Jenis" name="type" :value="$filters['type']" :options="$typeOptions" />
            <x-ui.select label="Status" name="status" :value="$filters['status']" :options="$statusOptions" />
            <x-ui.select label="Tahap" name="stage" :value="$filters['stage']" :options="$stageOptions" />
            <x-ui.select label="Urutkan" name="sort" :value="$filters['sort']" :options="$sortOptions" />

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Terapkan
                </button>
                <a href="{{ route('documents.inbox', ['tab' => $activeTab]) }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </x-ui.filter-bar>

        <div class="text-sm font-medium text-slate-500" data-inbox-count>
            Menampilkan {{ $loadedResultCount }} dari {{ $activeResultCount }} dokumen
        </div>

        @if ($activeTab === 'needs-process')
            <x-ui.panel
                title="Perlu Saya Proses"
                :padded="false"
            >
                <x-ui.scrollable-table max-height="560px" min-width="1080px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nomor</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dokumen</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Jenis</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tahap</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Pengaju</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dept</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tanggal</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tindakan</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" data-inbox-rows>
                        @if ($filteredMyTasks->isNotEmpty())
                            @include('document-management.partials.inbox-needs-process-rows', ['documents' => $filteredMyTasks])
                        @else
                            <tr>
                                <td colspan="10" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Tidak ada dokumen yang perlu diproses oleh Anda.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </x-ui.scrollable-table>

                @if ($hasMoreResults)
                    <div class="flex justify-center border-t border-slate-100 p-4">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            data-inbox-load-more
                            data-next-page="{{ $nextPage }}"
                            data-page-param="needs_page"
                            data-loaded="{{ $loadedResultCount }}"
                            data-total="{{ $activeResultCount }}"
                            data-loading-label="Memuat..."
                        >
                            Tampilkan Lebih Banyak
                        </button>
                    </div>
                @endif
            </x-ui.panel>
        @endif

        @if ($activeTab === 'processed-history')
            <x-ui.panel
                title="Riwayat yang Saya Proses"
                description="Dokumen yang approval-nya sudah pernah Anda proses."
                :padded="false"
            >
                <x-ui.scrollable-table max-height="560px" min-width="980px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nomor</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Dokumen</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Jenis</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Diajukan</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Tahap</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Pengaju</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Diproses</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                            <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" data-inbox-rows>
                        @if ($filteredMyProcessedHistory->isNotEmpty())
                            @include('document-management.partials.inbox-processed-history-rows', ['documents' => $filteredMyProcessedHistory])
                        @else
                            <tr>
                                <td colspan="9" class="px-5 py-10 text-center text-sm text-slate-500">
                                    Tidak ada riwayat pengajuan yang cocok dengan filter.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </x-ui.scrollable-table>

                @if ($hasMoreResults)
                    <div class="flex justify-center border-t border-slate-100 p-4">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            data-inbox-load-more
                            data-next-page="{{ $nextPage }}"
                            data-page-param="history_page"
                            data-loaded="{{ $loadedResultCount }}"
                            data-total="{{ $activeResultCount }}"
                            data-loading-label="Memuat..."
                        >
                            Tampilkan Lebih Banyak
                        </button>
                    </div>
                @endif
            </x-ui.panel>
        @endif
    </div>

    <script>
        (() => {
            const button = document.querySelector('[data-inbox-load-more]');

            if (! button || button.dataset.bound === 'true') {
                return;
            }

            button.dataset.bound = 'true';
            button.addEventListener('click', async () => {
                if (button.disabled) {
                    return;
                }

                const rows = document.querySelector('[data-inbox-rows]');
                const count = document.querySelector('[data-inbox-count]');
                const originalLabel = button.textContent.trim();
                const url = new URL(window.location.href);

                url.searchParams.set('load_more', '1');
                url.searchParams.set(button.dataset.pageParam, button.dataset.nextPage);

                button.disabled = true;
                button.textContent = button.dataset.loadingLabel || 'Memuat...';

                try {
                    const response = await fetch(url, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (! response.ok) {
                        throw new Error('Request failed');
                    }

                    const payload = await response.json();
                    rows.insertAdjacentHTML('beforeend', payload.rows);
                    button.dataset.loaded = String(payload.displayed_count);

                    if (count) {
                        count.textContent = `Menampilkan ${payload.displayed_count} dari ${payload.total} dokumen`;
                    }

                    if (! payload.has_more) {
                        button.closest('div')?.remove();

                        return;
                    }

                    button.dataset.nextPage = String(payload.next_page);
                    button.disabled = false;
                    button.textContent = originalLabel;
                } catch (error) {
                    button.disabled = false;
                    button.textContent = originalLabel;
                }
            });
        })();
    </script>
</x-layouts.app>
