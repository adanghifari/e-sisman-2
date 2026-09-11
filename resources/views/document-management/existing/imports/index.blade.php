<x-layouts.app :title="__('Arsip Dokumen Existing')">
    <div class="space-y-6">
        <x-ui.page-header
            title="Arsip Dokumen Existing"
            description="Daftar dokumen obsolete hasil import manual."
        />

        <x-ui.filter-bar :action="route('documents.existing.imports.index')">
            <x-ui.input
                label="Cari"
                name="search"
                :value="$filters['search']"
                placeholder="Cari nama, nomor, revisi, level, atau proses..."
            />
            <x-ui.select label="Jenis Ketentuan" name="rule" :value="$filters['rule']" :options="$ruleOptions" />
            <x-ui.select label="Proses Bisnis" name="process" :value="$filters['process']" :options="$processOptions" />

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Terapkan
                </button>
                <a href="{{ route('documents.existing.imports.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </x-ui.filter-bar>

        @if ($canCreateImportedExisting)
            <div class="flex justify-end">
                <a
                    href="{{ route('documents.obsolete.imports.create') }}"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
                >
                    <x-flux.icon name="plus" class="size-4" />
                    Tambah Arsip Obsolete Legacy
                </a>
            </div>
        @endif

        <x-ui.panel
            title="Daftar Arsip"
            description="Menampilkan {{ $documents->count() }} dokumen obsolete hasil import manual."
            :padded="false"
        >
            <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-base">
                <colgroup>
                    <col class="w-[28%]">
                    <col class="w-[16%]">
                    <col class="w-[10%]">
                    <col class="w-[18%]">
                    <col class="w-[14%]">
                    <col class="w-[10%]">
                </colgroup>
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nama Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Nomor Dokumen</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Revisi</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Ketentuan</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-3 py-3 font-semibold">Tgl Obsolete</th>
                        <th class="sticky top-0 z-10 bg-slate-50 px-2 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($documents as $document)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-3 py-4">
                                <p class="font-semibold uppercase tracking-wide text-slate-800">{{ $document->nama_dokumen }}</p>
                                <p class="mt-1 text-xs font-medium text-slate-500">{{ $document->businessProcess?->nama_proses_bisnis ?: 'Tidak dipetakan' }}</p>
                            </td>
                            <td class="px-3 py-4 font-semibold text-slate-700">{{ $document->nomor_dokumen ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document->nomor_revisi ?: '-' }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $ruleOptions[$document->obsolete_rule_type] ?? $document->obsolete_rule_type }}</td>
                            <td class="px-3 py-4 text-slate-600">{{ $document->tanggal_obsolete?->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-2 py-4">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon-button :href="route('documents.existing.imports.show', $document)" icon="eye" label="Lihat detail" size="sm" />
                                    @if ($canDeleteImportedExisting)
                                        <form
                                            method="POST"
                                            action="{{ route('documents.existing.imports.destroy', $document) }}"
                                            onsubmit="return confirm('Hapus dokumen imported ini dari sistem?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex size-9 items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 transition hover:bg-red-50"
                                                aria-label="Hapus dokumen imported"
                                                title="Hapus dokumen imported"
                                            >
                                                <x-flux.icon name="trash" class="size-4" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">
                                Belum ada Arsip Dokumen Existing.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.scrollable-table>
        </x-ui.panel>
    </div>
</x-layouts.app>

