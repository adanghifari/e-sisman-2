<x-layouts.app :title="__('Overview')">
    <div class="space-y-6">
        <x-ui.page-header
            title="Overview"
            description="Daftar dokumen master untuk kebutuhan laporan."
        />

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($overviewSummary as $card)
                @php
                    $toneClass = [
                        'sky' => 'border-slate-200 bg-white',
                        'emerald' => 'border-slate-200 bg-white',
                        'violet' => 'border-slate-200 bg-white',
                    ][$card['tone']] ?? 'border-slate-200 bg-white';
                    $iconClass = [
                        'sky' => 'bg-sky-100 text-sky-700',
                        'emerald' => 'bg-emerald-100 text-emerald-700',
                        'violet' => 'bg-violet-100 text-violet-700',
                    ][$card['tone']] ?? 'bg-slate-100 text-slate-700';
                @endphp

                <div class="rounded-lg border p-5 shadow-sm {{ $toneClass }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</p>
                            <p class="mt-3 text-3xl font-bold text-slate-950">{{ $card['value'] }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-500">{{ $card['hint'] }}</p>
                        </div>
                        <div class="grid size-10 place-items-center rounded-lg {{ $iconClass }}">
                            <x-flux.icon name="document-text" class="size-5" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)]">
            <x-ui.panel title="Statistik Dokumen" class="h-full">
                <x-slot name="actions">
                    <form method="GET" action="{{ route('reports.index') }}" class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                        @foreach ($overviewFilters as $key => $value)
                            @if ($key !== 'year' && filled($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach

                        <select
                            name="year"
                            class="h-8 rounded-md border border-slate-200 bg-slate-50 px-2 text-xs font-semibold text-slate-600 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                            onchange="this.form.submit()"
                        >
                            @foreach ($yearOptions as $yearValue => $yearLabel)
                                <option value="{{ $yearValue }}" @selected((string) $overviewFilters['year'] === (string) $yearValue)>
                                    {{ $yearLabel }}
                                </option>
                            @endforeach
                        </select>
                        <span class="rounded-md bg-slate-100 px-2.5 py-1">Bulanan</span>
                    </form>
                </x-slot>

                <div class="mt-5">
                    <div class="rounded-lg border border-slate-200 bg-white px-5 py-6">
                        <svg viewBox="0 0 980 280" role="img" aria-label="Statistik dokumen" class="h-[360px] w-full overflow-visible">
                            <defs>
                                <linearGradient id="overviewTrendArea" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.02" />
                                </linearGradient>
                                <filter id="overviewTrendGlow" x="-20%" y="-20%" width="140%" height="140%">
                                    <feGaussianBlur stdDeviation="1.8" result="coloredBlur" />
                                    <feMerge>
                                        <feMergeNode in="coloredBlur" />
                                        <feMergeNode in="SourceGraphic" />
                                    </feMerge>
                                </filter>
                            </defs>

                            @foreach ([60, 102.5, 145, 187.5, 230] as $lineY)
                                <line x1="24" y1="{{ $lineY }}" x2="956" y2="{{ $lineY }}" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="5 8" />
                            @endforeach

                            @foreach ($trendStatistics['items'] as $item)
                                <rect x="{{ $item['x'] - 9 }}" y="{{ $item['y'] }}" width="18" height="{{ 230 - $item['y'] }}" rx="9" fill="#bae6fd" opacity="0.5" />
                            @endforeach

                            @if ($trendStatistics['area_path'] !== '')
                                <path d="{{ $trendStatistics['area_path'] }}" fill="url(#overviewTrendArea)" />
                            @endif

                            @if ($trendStatistics['smooth_path'] !== '')
                                <path d="{{ $trendStatistics['smooth_path'] }}" fill="none" stroke="#60a5fa" stroke-width="18" stroke-linecap="round" stroke-linejoin="round" opacity="0.12" />
                                <path d="{{ $trendStatistics['smooth_path'] }}" fill="none" stroke="#2563eb" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" filter="url(#overviewTrendGlow)" />
                            @endif

                            @foreach ($trendStatistics['items'] as $item)
                                @if ($item['value'] > 0)
                                    <circle cx="{{ $item['x'] }}" cy="{{ $item['y'] }}" r="8" fill="#2563eb" />
                                    <circle cx="{{ $item['x'] }}" cy="{{ $item['y'] }}" r="17" fill="#2563eb" opacity="0.12" />
                                    <text x="{{ $item['x'] }}" y="{{ max(26, $item['y'] - 22) }}" text-anchor="middle" class="fill-slate-700 text-[13px] font-bold">{{ $item['value'] }}</text>
                                @endif
                                <text x="{{ $item['x'] }}" y="265" text-anchor="middle" class="fill-slate-400 text-[12px] font-semibold">{{ $item['label'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                </div>
            </x-ui.panel>

            <x-ui.panel title="Total Dokumen" class="h-full">
                <div class="mt-5 grid h-full min-h-[380px] place-items-center">
                    <div class="relative mx-auto grid size-80 place-items-center rounded-full" style="background: {{ $businessFunctionStatistics['chart'] }}">
                        <div class="grid size-52 place-items-center rounded-full bg-white text-center shadow-inner">
                            <div>
                                <span class="block text-4xl font-bold text-slate-950">{{ $businessFunctionStatistics['total'] }}</span>
                                <span class="block text-xs font-semibold text-slate-500">Dokumen</span>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.panel>
        </div>

        <x-ui.filter-bar :action="route('reports.index')">
            <x-ui.input
                label="Cari Prosedur / Nomor"
                name="procedure"
                :value="$overviewFilters['procedure']"
                placeholder="Cari prosedur atau nomor dokumen..."
            />

            <x-ui.input
                label="Cari Instruksi Kerja"
                name="instruction"
                :value="$overviewFilters['instruction']"
                placeholder="Cari instruksi kerja..."
            />

            <x-ui.select
                label="Department"
                name="department_id"
                :value="$overviewFilters['department_id']"
                :options="$departmentOptions"
            />

            <x-ui.select
                label="Proses / Fungsi"
                name="business_function_id"
                :value="$overviewFilters['business_function_id']"
                :options="$businessFunctionOptions"
            />

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Terapkan
                </button>
                <a href="{{ route('reports.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </x-ui.filter-bar>

        <x-ui.panel
            title="Daftar Dokumen"
            description="Menampilkan {{ $overviewRows->count() }} dari {{ $overviewRows->total() }} prosedur."
            :padded="false"
        >
            <x-slot name="actions">
                <a href="{{ route('reports.export', array_filter($overviewFilters, fn ($value) => $value !== '')) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    <x-flux.icon name="arrow-down-tray" class="size-4" />
                    Export Dokumen
                </a>
            </x-slot>

            <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-sm">
                <colgroup>
                    <col class="w-[4%]">
                    <col class="w-[28%]">
                    <col class="w-[14%]">
                    <col class="w-[8%]">
                    <col class="w-[20%]">
                    <col class="w-[17%]">
                    <col class="w-[9%]">
                </colgroup>

                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-2 py-3 font-semibold"></th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Prosedur</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nomor Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Revisi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Department</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Proses/Fungsi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Tgl Terbit</th>
                    </tr>
                </thead>

                @forelse ($overviewRows as $row)
                    @php
                        $rowKey = 'overview-procedure-'.$row['id'];
                        $instructions = $row['instructions'];
                    @endphp

                    <tbody class="is-row-group border-b border-slate-100">
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-1 py-4">
                                @if ($instructions->isNotEmpty())
                                    <x-ui.icon-button
                                        icon="chevron-right"
                                        label="Tampilkan instruksi kerja"
                                        variant="ghost"
                                        size="sm"
                                        data-table-row-toggle="{{ $rowKey }}"
                                        aria-expanded="false"
                                    />
                                @endif
                            </td>
                            <td class="px-3 py-4">
                                <p class="font-semibold text-slate-800">{{ $row['procedure'] }}</p>
                                <p class="mt-1 text-xs font-medium text-slate-500">{{ $instructions->count() }} Instruksi Kerja</p>
                            </td>
                            <td class="px-3 py-4 font-semibold text-slate-700">{{ $row['number'] ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $row['revision'] }}</td>
                            <td class="px-3 py-4 text-slate-600">
                                {{ $row['departments']->isNotEmpty() ? $row['departments']->join(', ') : '-' }}
                            </td>
                            <td class="px-3 py-4 text-slate-600">{{ $row['business_function'] }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $row['published_at'] }}</td>
                        </tr>

                        @if ($instructions->isNotEmpty())
                            <tr class="is-child-row hidden bg-slate-50/40" data-table-row-target="{{ $rowKey }}">
                                <td colspan="7" class="px-0 py-0">
                                    <div class="relative py-3 pl-14 pr-5">
                                        <span class="absolute left-6 top-0 h-1/2 border-l border-slate-300"></span>
                                        <span class="absolute left-6 top-1/2 w-8 border-t border-slate-300"></span>

                                        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm shadow-slate-100/70">
                                            <table class="w-full table-fixed text-sm">
                                                <colgroup>
                                                    <col class="w-[30%]">
                                                    <col class="w-[15%]">
                                                    <col class="w-[10%]">
                                                    <col class="w-[23%]">
                                                    <col class="w-[14%]">
                                                    <col class="w-[8%]">
                                                </colgroup>
                                                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                                    <tr>
                                                        <th class="px-5 py-3 text-left font-semibold">Instruksi Kerja</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Nomor Dokumen</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Revisi</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Department</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Proses/Fungsi</th>
                                                        <th class="px-5 py-3 text-left font-semibold">Tgl Terbit</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach ($instructions as $instruction)
                                                        <tr>
                                                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $instruction['name'] }}</td>
                                                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $instruction['number'] ?: '-' }}</td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $instruction['revision'] }}</td>
                                                            <td class="px-5 py-4 text-slate-600">
                                                                {{ $instruction['departments']->isNotEmpty() ? $instruction['departments']->join(', ') : '-' }}
                                                            </td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $instruction['business_function'] }}</td>
                                                            <td class="px-5 py-4 text-slate-600">{{ $instruction['published_at'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                                Tidak ada dokumen yang cocok dengan filter.
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </x-ui.scrollable-table>

            @if ($overviewRows->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $overviewRows->links() }}
                </div>
            @endif
        </x-ui.panel>
    </div>
</x-layouts.app>
