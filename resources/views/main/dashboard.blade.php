<x-layouts.app :title="__('Dashboard')">
    <div class="space-y-6">
        <x-ui.page-header title="Dashboard">
            <x-ui.action-button :href="route('documents.create')">
                Tambah Dokumen
            </x-ui.action-button>
        </x-ui.page-header>

        <div class="grid items-stretch gap-4 sm:grid-cols-2">
            @foreach ($summaryCards as $card)
                <a href="{{ route('documents.inbox', ['tab' => $card['tab']]) }}" class="block transition hover:-translate-y-0.5 hover:shadow-sm">
                    <x-ui.summary-card
                        :label="$card['label']"
                        :value="$card['value']"
                        :hint="$card['hint']"
                    />
                </a>
            @endforeach
        </div>

        <x-ui.panel
            title="Dokumen Butuh Diproses"
            description="Dokumen yang perlu ditindaklanjuti oleh Admin."
            :padded="false"
        >
            <x-slot name="actions">
                <a href="{{ route('documents.inbox') }}" class="shrink-0 text-sm font-semibold text-sky-700">Lihat semua</a>
            </x-slot>

            <x-ui.scrollable-table :min-width="null" :horizontal="false" class="table-fixed">
                <colgroup>
                    <col class="w-[13%]">
                    <col class="w-[24%]">
                    <col class="w-[10%]">
                    <col class="w-[15%]">
                    <col class="w-[14%]">
                    <col class="w-[12%]">
                    <col class="w-[12%]">
                </colgroup>
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-4 py-3 font-semibold">Nomor</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-4 py-3 font-semibold">Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-4 py-3 font-semibold">Jenis</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-4 py-3 font-semibold">Tahap</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-4 py-3 font-semibold">Pengaju</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-4 py-3 font-semibold">Tanggal</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-4 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($needsProcessDocuments as $document)
                        <tr class="hover:bg-slate-50/70">
                            <td class="break-words px-4 py-4 align-top font-semibold leading-5 text-slate-800">{{ $document['number'] }}</td>
                            <td class="break-words px-4 py-4 align-top leading-5 text-slate-700">{{ $document['name'] }}</td>
                            <td class="break-words px-4 py-4 align-top text-slate-600">{{ $document['type'] }}</td>
                            <td class="break-words px-4 py-4 align-top leading-5 text-slate-600">{{ $document['stage'] }}</td>
                            <td class="break-words px-4 py-4 align-top leading-5 text-slate-600">{{ $document['owner'] }}</td>
                            <td class="break-words px-4 py-4 align-top leading-5 text-slate-600">{{ $document['date'] }}</td>
                            <td class="px-4 py-4 align-top">
                                <x-ui.status-badge :label="$document['status']" :tone="$document['tone'] ?? 'sky'" class="whitespace-nowrap" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm font-medium text-slate-500">
                                Tidak ada dokumen yang perlu diproses.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.scrollable-table>
        </x-ui.panel>

        <div class="grid items-stretch gap-6 xl:grid-cols-2">
            <x-ui.panel
                title="Statistik Level Dokumen"
                class="h-full"
            >
                <div class="mt-5 flex h-full flex-col gap-5">
                    <div class="relative mx-auto grid size-56 place-items-center rounded-full" style="background: {{ $levelStatistics['chart'] }}">
                        @foreach ($levelStatistics['items'] as $item)
                            @if ($item['value'] > 0)
                                <span
                                    class="absolute -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/95 px-2 py-0.5 text-[11px] font-bold text-slate-700 shadow-sm ring-1 ring-slate-200"
                                    style="left: {{ $item['label_x'] }}%; top: {{ $item['label_y'] }}%;"
                                >
                                    {{ $item['percentage'] }}%
                                </span>
                            @endif
                        @endforeach

                        <div class="grid size-40 place-items-center rounded-full bg-white text-center shadow-inner">
                            <span class="block text-2xl font-bold text-slate-950">{{ $levelStatistics['total'] }}</span>
                            <span class="block text-xs font-semibold text-slate-500">Dokumen</span>
                        </div>
                    </div>

                    <x-ui.scroll-area max-height="172px" class="pr-2">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($levelStatistics['items'] as $item)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 block size-2.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium leading-snug text-slate-600">{{ $item['label'] }}</p>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $item['value'] }} dokumen</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.scroll-area>
                </div>
            </x-ui.panel>

            <x-ui.panel
                title="Statistik Proses/Fungsi Dokumen"
                class="h-full"
            >
                <div class="mt-5 flex h-full flex-col gap-5">
                    <div class="relative mx-auto grid size-56 place-items-center rounded-full" style="background: {{ $businessFunctionStatistics['chart'] }}">
                        @foreach ($businessFunctionStatistics['items'] as $item)
                            @if ($item['value'] > 0)
                                <span
                                    class="absolute -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/95 px-2 py-0.5 text-[11px] font-bold text-slate-700 shadow-sm ring-1 ring-slate-200"
                                    style="left: {{ $item['label_x'] }}%; top: {{ $item['label_y'] }}%;"
                                >
                                    {{ $item['percentage'] }}%
                                </span>
                            @endif
                        @endforeach

                        <div class="grid size-40 place-items-center rounded-full bg-white text-center shadow-inner">
                            <span class="block text-2xl font-bold text-slate-950">{{ $businessFunctionStatistics['total'] }}</span>
                            <span class="block text-xs font-semibold text-slate-500">Dokumen</span>
                        </div>
                    </div>

                    <x-ui.scroll-area max-height="172px" class="pr-2">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($businessFunctionStatistics['items'] as $item)
                                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 block size-2.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium leading-snug text-slate-600">{{ $item['label'] }}</p>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $item['value'] }} dokumen</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.scroll-area>
                </div>
            </x-ui.panel>
        </div>

        <x-ui.panel
            title="Aktivitas Terbaru"
            description="Log download terbaru."
        >
            <x-slot name="actions">
                <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ $activities->count() }} item
                </span>
            </x-slot>

            <x-ui.scroll-area max-height="210px" class="mt-4 space-y-4 pr-2">
                @forelse ($activities as $activity)
                    <div class="flex gap-3">
                        <span class="mt-1.5 size-2 rounded-full {{ $activity['is_obsolete'] ? 'bg-red-500' : 'bg-sky-500' }}"></span>
                        <div class="min-w-0">
                            <p class="text-sm leading-5 text-slate-700">
                                {{ $activity['downloaded_by'] }} mengunduh
                                <span class="font-semibold {{ $activity['is_obsolete'] ? 'text-red-600' : 'text-slate-700' }}">{{ $activity['display_number'] ?? $activity['number'] }}</span>
                                - {{ $activity['name'] }}
                            </p>
                            <p class="mt-1 text-xs font-medium text-slate-400">{{ $activity['time'] }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center text-sm font-medium text-slate-500">
                        Belum ada aktivitas terbaru.
                    </div>
                @endforelse
            </x-ui.scroll-area>
        </x-ui.panel>

        <div class="grid items-stretch gap-6 xl:grid-cols-2">
            <x-ui.panel title="Total Dokumen - Proses Bisnis" class="h-full">
                <x-slot name="actions">
                    <a href="{{ route('documents.master') }}" class="shrink-0 text-xs font-semibold text-sky-700 hover:text-sky-800">
                        Lihat semua
                    </a>
                </x-slot>

                <x-ui.scroll-area max-height="320px" class="mt-4 space-y-4 pr-2">
                    @forelse ($businessProcessTotals['items'] as $item)
                        <div>
                            <div class="flex items-start justify-between gap-3 text-sm">
                                <p class="font-medium leading-snug text-slate-700">{{ $item['label'] }}</p>
                                <p class="shrink-0 font-semibold text-slate-600">
                                    {{ $item['value'] }} Dokumen ({{ $item['percentage'] }}%)
                                </p>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-sky-500" style="width: {{ $item['percentage'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center text-sm font-medium text-slate-500">
                            Belum ada data proses bisnis.
                        </div>
                    @endforelse
                </x-ui.scroll-area>
            </x-ui.panel>

            <x-ui.panel title="Total Dokumen - Department" class="h-full">
                <x-slot name="actions">
                    <a href="{{ route('documents.master') }}" class="shrink-0 text-xs font-semibold text-sky-700 hover:text-sky-800">
                        Lihat semua
                    </a>
                </x-slot>

                <x-ui.scroll-area max-height="320px" class="mt-4 space-y-4 pr-2">
                    @forelse ($departmentTotals['items'] as $item)
                        <div>
                            <div class="flex items-start justify-between gap-3 text-sm">
                                <p class="font-medium leading-snug text-slate-700">{{ $item['label'] }}</p>
                                <p class="shrink-0 font-semibold text-slate-600">
                                    {{ $item['value'] }} Dokumen ({{ $item['percentage'] }}%)
                                </p>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-sky-500" style="width: {{ $item['percentage'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center text-sm font-medium text-slate-500">
                            Belum ada data department.
                        </div>
                    @endforelse
                </x-ui.scroll-area>
            </x-ui.panel>
        </div>

        <footer class="pt-2 text-center text-xs font-medium text-slate-400">
            &copy; 2026 E-SISMAN. Dikembangkan oleh Internship Prakerin KBS.
        </footer>
    </div>
</x-layouts.app>
