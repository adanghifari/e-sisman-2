<div class="space-y-6">
    <x-ui.page-header title="Jenis Dokumen" />

    <x-master-data.index-panel
        title="List Jenis Dokumen"
        description="Menampilkan {{ $documentTypes->count() }} data dari total {{ $documentTypes->total() }} jenis dokumen."
        search-label="Cari Jenis Dokumen"
        search-placeholder="Cari jenis dokumen..."
        :status-options="$statusOptions"
        :status="$status"
        :can-create="$this->canCreate"
    >
        <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-base">
            <colgroup>
                <col class="w-[62%]">
                <col class="w-[16%]">
                <col class="w-[22%]">
            </colgroup>

            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Jenis Dokumen</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documentTypes as $documentType)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-semibold text-slate-800">{{ $documentType->nama_types }}</td>
                        <td class="px-5 py-4">
                            <x-ui.status-badge
                                :label="$documentType->is_active ? 'Active' : 'Inactive'"
                                :tone="$documentType->is_active ? 'sky' : 'slate'"
                            />
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-start gap-3">
                                @if ($this->canUpdate)
                                    <x-ui.icon-button
                                        icon="pencil"
                                        label="Edit jenis dokumen"
                                        size="sm"
                                        wire:click="edit({{ $documentType->id }})"
                                    />

                                    <x-ui.inline-status-toggle
                                        :active="$documentType->is_active"
                                        wire:click="toggleStatus({{ $documentType->id }})"
                                        aria-label="{{ $documentType->is_active ? 'Nonaktifkan jenis dokumen' : 'Aktifkan jenis dokumen' }}"
                                    />
                                @endif

                                @if ($this->canDelete)
                                    <x-ui.icon-button
                                        icon="trash"
                                        label="Hapus jenis dokumen"
                                        size="sm"
                                        wire:click="confirmDelete({{ $documentType->id }})"
                                    />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500">
                            Tidak ada jenis dokumen yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.scrollable-table>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $documentTypes->links() }}
        </div>
    </x-master-data.index-panel>

    @if ($showForm)
        <x-ui.modal
            :title="$editingId ? 'Edit Jenis Dokumen' : 'Tambah Jenis Dokumen'"
            description="Lengkapi nama dan status jenis dokumen."
            close-action="cancel"
            max-width="lg"
        >
            <form wire:submit.prevent="save" class="space-y-5 px-6 py-5">
                <x-ui.form-input
                    label="Nama Jenis Dokumen"
                    name="nama_types"
                    wire:model="nama_types"
                    placeholder="Contoh: Instruksi Kerja"
                />

                <x-ui.status-toggle
                    :active="$is_active"
                    name="is_active"
                    wire:model="is_active"
                    active-description="Data aktif dan bisa dipilih saat pengajuan dokumen."
                    inactive-description="Data nonaktif tidak ditampilkan sebagai pilihan aktif."
                />

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-5">
                    <x-ui.action-button type="button" variant="secondary" wire:click="cancel">
                        Batal
                    </x-ui.action-button>

                    <x-ui.action-button type="submit">
                        Simpan
                    </x-ui.action-button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showDeleteModal)
        <x-ui.confirm-modal
            title="Hapus Jenis Dokumen"
            description="Data yang belum digunakan dokumen akan dihapus permanen."
            message="Yakin ingin menghapus jenis dokumen ini?"
            confirm-action="delete"
            cancel-action="cancelDelete"
            error-key="delete"
        />
    @endif
</div>
