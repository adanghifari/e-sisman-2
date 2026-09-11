<div class="space-y-6">
    <x-ui.page-header title="Proses Bisnis" />

    <x-master-data.index-panel
        title="List Proses Bisnis"
        description="Menampilkan {{ $businessProcesses->count() }} data dari total {{ $businessProcesses->total() }} proses bisnis."
        search-label="Cari Proses Bisnis"
        search-placeholder="Cari kode atau proses bisnis..."
        :status-options="$statusOptions"
        :status="$status"
        :can-create="$this->canCreate"
    >
        <x-ui.scrollable-table max-height="620px" min-width="100%" :horizontal="false" class="table-fixed text-base">
            <colgroup>
                <col class="w-[16%]">
                <col class="w-[49%]">
                <col class="w-[15%]">
                <col class="w-[20%]">
            </colgroup>

            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Kode</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Proses Bisnis</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($businessProcesses as $businessProcess)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-semibold text-slate-800">{{ $businessProcess->kode }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $businessProcess->nama_proses_bisnis }}</td>
                        <td class="px-5 py-4">
                            <x-ui.status-badge
                                :label="$businessProcess->is_active ? 'Active' : 'Inactive'"
                                :tone="$businessProcess->is_active ? 'sky' : 'slate'"
                            />
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-start gap-3">
                                @if ($this->canUpdate)
                                    <x-ui.icon-button
                                        icon="pencil"
                                        label="Edit proses bisnis"
                                        size="sm"
                                        wire:click="edit({{ $businessProcess->id }})"
                                    />

                                    <x-ui.inline-status-toggle
                                        :active="$businessProcess->is_active"
                                        wire:click="toggleStatus({{ $businessProcess->id }})"
                                        aria-label="{{ $businessProcess->is_active ? 'Nonaktifkan proses bisnis' : 'Aktifkan proses bisnis' }}"
                                    />
                                @endif

                                @if ($this->canDelete)
                                    <x-ui.icon-button
                                        icon="trash"
                                        label="Hapus proses bisnis"
                                        size="sm"
                                        wire:click="confirmDelete({{ $businessProcess->id }})"
                                    />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                            Tidak ada proses bisnis yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.scrollable-table>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $businessProcesses->links() }}
        </div>
    </x-master-data.index-panel>

    @if ($showForm)
        <x-ui.modal
            :title="$editingId ? 'Edit Proses Bisnis' : 'Tambah Proses Bisnis'"
            description="Lengkapi kode, nama, dan status proses bisnis."
            close-action="cancel"
        >
            <form wire:submit.prevent="save" class="space-y-5 px-6 py-5">
                <div class="grid gap-4 md:grid-cols-[160px_1fr]">
                    <x-ui.form-input
                        label="Kode"
                        name="kode"
                        wire:model="kode"
                        placeholder="Contoh: MRI"
                    />

                    <x-ui.form-input
                        label="Nama Proses Bisnis"
                        name="nama_proses_bisnis"
                        wire:model="nama_proses_bisnis"
                        placeholder="Nama proses bisnis"
                    />
                </div>

                <x-ui.status-toggle
                    :active="$is_active"
                    name="is_active"
                    wire:model="is_active"
                    active-description="Data aktif dan bisa dipilih di dokumen."
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
            title="Hapus Proses Bisnis"
            description="Data yang belum digunakan dokumen akan dihapus permanen."
            message="Yakin ingin menghapus proses bisnis ini?"
            confirm-action="delete"
            cancel-action="cancelDelete"
            error-key="delete"
        />
    @endif
</div>
