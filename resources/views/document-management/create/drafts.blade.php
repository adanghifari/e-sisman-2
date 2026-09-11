<x-layouts.app :title="__('Draft Saya')">
    <div class="space-y-8">
        <nav class="flex items-center gap-3 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700">Home</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <a href="{{ route('documents.create') }}" class="transition hover:text-sky-700">Tambah Dokumen</a>
            <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
            <span class="text-slate-700">Draft Saya</span>
        </nav>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <x-ui.page-header
                title="Draft Saya"
                description="Daftar dokumen yang sudah disimpan dan belum diajukan."
            />

            <a href="{{ route('documents.create') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-blue-500 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-600">
                <x-flux.icon name="plus" class="size-5" />
                Tambah Dokumen
            </a>
        </div>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Dokumen</th>
                            <th class="px-5 py-3">Nomor</th>
                            <th class="px-5 py-3">Level</th>
                            <th class="px-5 py-3">Proses</th>
                            <th class="px-5 py-3">File</th>
                            <th class="px-5 py-3">Dibuat</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($drafts as $draft)
                            <tr class="align-top">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-800">{{ $draft->nama_dokumen }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $draft->departments->pluck('kode_department')->filter()->implode(', ') ?: '-' }}</p>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-700">{{ $draft->nomor_dokumen ?: '-' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $draft->documentLevel?->nama_level ?: '-' }}</td>
                                <td class="px-5 py-4 text-slate-600">
                                    <p>{{ $draft->businessProcess ? (($draft->businessProcess->kode ? $draft->businessProcess->kode.' - ' : '').$draft->businessProcess->nama_proses_bisnis) : '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $draft->businessFunction ? (($draft->businessFunction->kode ? $draft->businessFunction->kode.' - ' : '').$draft->businessFunction->nama_proses_fungsi) : '-' }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $draft->files->count() }} file</td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ $draft->created_at ? \Illuminate\Support\Carbon::parse($draft->created_at)->translatedFormat('d M Y H:i:s') : '-' }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('documents.create.drafts.edit', $draft) }}" class="inline-flex h-9 items-center justify-center rounded-lg bg-sky-600 px-3 text-xs font-bold text-white transition hover:bg-sky-700">
                                            Lanjutkan
                                        </a>
                                        <button
                                            type="button"
                                            class="inline-flex h-9 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-xs font-bold text-red-600 transition hover:bg-red-50"
                                            data-draft-delete-open="{{ $draft->id }}"
                                        >
                                            Hapus
                                        </button>
                                    </div>

                                    <div class="hidden" data-draft-delete-modal="{{ $draft->id }}">
                                        <x-ui.modal
                                            title="Hapus Draft"
                                            :description="'Draft '.($draft->nama_dokumen ?: 'tanpa judul').' akan dihapus permanen.'"
                                            max-width="md"
                                        >
                                            <div class="space-y-4 px-6 py-5 text-left">
                                                <p class="text-sm leading-6 text-slate-700">
                                                    Yakin ingin menghapus draft ini? File yang sudah diunggah di draft juga akan ikut dihapus dan tidak bisa dikembalikan.
                                                </p>
                                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                                                    <p class="text-sm font-semibold text-slate-900">{{ $draft->nama_dokumen ?: 'Draft tanpa judul' }}</p>
                                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $draft->nomor_dokumen ?: '-' }}</p>
                                                </div>
                                            </div>

                                            <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                                                <x-ui.action-button type="button" variant="secondary" data-draft-delete-close>
                                                    Batal
                                                </x-ui.action-button>

                                                <form method="POST" action="{{ route('documents.create.drafts.destroy', $draft) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                                                        Hapus Draft
                                                    </button>
                                                </form>
                                            </div>
                                        </x-ui.modal>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">Belum ada draft dokumen.</p>
                                    <p class="mt-1 text-xs font-medium text-slate-500">Draft akan muncul di sini setelah disimpan dari form tambah dokumen.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @once
        <script>
            (() => {
                const closeModal = (modal) => {
                    modal?.classList.add('hidden');
                };

                document.addEventListener('click', (event) => {
                    const openButton = event.target.closest('[data-draft-delete-open]');

                    if (openButton) {
                        document
                            .querySelector(`[data-draft-delete-modal="${openButton.dataset.draftDeleteOpen}"]`)
                            ?.classList.remove('hidden');

                        return;
                    }

                    const closeButton = event.target.closest('[data-draft-delete-close]');

                    if (closeButton) {
                        closeModal(closeButton.closest('[data-draft-delete-modal]'));

                        return;
                    }

                    const overlay = event.target.closest('.app-modal-overlay');

                    if (overlay && event.target === overlay) {
                        closeModal(overlay.closest('[data-draft-delete-modal]'));
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape') {
                        return;
                    }

                    document
                        .querySelectorAll('[data-draft-delete-modal]:not(.hidden)')
                        .forEach((modal) => closeModal(modal));
                });
            })();
        </script>
    @endonce
</x-layouts.app>
