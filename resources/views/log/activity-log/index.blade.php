<x-layouts.app :title="__('Catatan Aktivitas')">
    <div class="space-y-6">
        <x-ui.page-header title="Catatan Aktivitas" />

        <x-ui.panel
            title="List Aktivitas"
            description="Menampilkan {{ $activities->count() }} aktivitas dari total {{ $totalActivities }} aktivitas."
            :padded="false"
        >
            <x-slot name="actions">
                <a
                    href="{{ route('activity-log.export', array_filter($filters, fn ($value) => $value !== '')) }}"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
                >
                    <x-flux.icon name="arrow-down-tray" class="size-4" />
                    Export Dokumen
                </a>
            </x-slot>

            <form method="GET" action="{{ route('activity-log.index') }}" class="grid gap-4 border-b border-slate-200 p-5 lg:grid-cols-3">
                <x-ui.input
                    label="Nama Dokumen"
                    name="document_name"
                    :value="$filters['document_name']"
                    placeholder="Cari nama dokumen"
                />

                <x-ui.input
                    label="Nomor Dokumen"
                    name="document_number"
                    :value="$filters['document_number']"
                    placeholder="Cari nomor dokumen"
                />

                <x-ui.input
                    label="Diunduh Oleh"
                    name="downloaded_by"
                    :value="$filters['downloaded_by']"
                    placeholder="Cari diunduh oleh"
                />
            </form>

            <x-ui.scrollable-table max-height="620px" min-width="1000px" class="text-base">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nomor Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Revisi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Diunduh Oleh</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Waktu Unduh</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Unduhan Ke</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($activities as $activity)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-5 font-semibold uppercase leading-7 text-slate-800">{{ $activity['name'] }}</td>
                            <td class="px-5 py-5 font-semibold text-slate-700">{{ $activity['number'] }}</td>
                            <td class="px-5 py-5 text-slate-600">{{ $activity['revision'] }}</td>
                            <td class="px-5 py-5 font-medium uppercase leading-7 text-slate-700">{{ $activity['downloaded_by'] }}</td>
                            <td class="px-5 py-5 text-slate-600">{{ $activity['downloaded_at'] }}</td>
                            <td class="px-5 py-5 font-semibold text-slate-700">{{ $activity['count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada aktivitas yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.scrollable-table>
        </x-ui.panel>
    </div>
</x-layouts.app>
