<div class="space-y-6">
    <x-ui.page-header title="Department" />

    <x-master-data.index-panel
        title="List Department"
        description="Menampilkan {{ $departments->count() }} data dari total {{ $departments->total() }} department."
        search-label="Cari Department"
        search-placeholder="Cari kode atau department..."
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
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Nama Department</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Status</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($departments as $department)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-semibold text-slate-800">{{ $department->kode_department }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $department->nama_department }}</td>
                        <td class="px-5 py-4">
                            <x-ui.status-badge
                                :label="$department->is_active ? 'Active' : 'Inactive'"
                                :tone="$department->is_active ? 'sky' : 'slate'"
                            />
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-start gap-3">
                                @if ($this->canUpdate)
                                    <x-ui.icon-button
                                        icon="pencil"
                                        label="Edit department"
                                        size="sm"
                                        wire:click="edit({{ $department->id }})"
                                    />

                                    <x-ui.inline-status-toggle
                                        :active="$department->is_active"
                                        wire:click="toggleStatus({{ $department->id }})"
                                        aria-label="{{ $department->is_active ? 'Nonaktifkan department' : 'Aktifkan department' }}"
                                    />
                                @endif

                                @if ($this->canDelete)
                                    <x-ui.icon-button
                                        icon="trash"
                                        label="Hapus department"
                                        size="sm"
                                        wire:click="confirmDelete({{ $department->id }})"
                                    />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                            Tidak ada department yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.scrollable-table>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $departments->links() }}
        </div>
    </x-master-data.index-panel>

    @if ($showForm)
        <x-ui.modal
            :title="$editingId ? 'Edit Department' : 'Tambah Department'"
            description="Lengkapi kode, nama, dan status department."
            close-action="cancel"
        >
            <form wire:submit.prevent="save" class="space-y-5 px-6 py-5">
                <div class="grid gap-4 md:grid-cols-[160px_1fr]">
                    <x-ui.form-input
                        label="Kode"
                        name="kode_department"
                        wire:model="kode_department"
                        placeholder="Contoh: HSSE"
                    />

                    <x-ui.form-input
                        label="Nama Department"
                        name="nama_department"
                        wire:model="nama_department"
                        placeholder="Nama department"
                    />
                </div>

                <x-ui.status-toggle
                    :active="$is_active"
                    name="is_active"
                    wire:model="is_active"
                    active-description="Data aktif dan bisa dipilih pada dokumen atau user."
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
            title="Hapus Department"
            description="Data yang belum digunakan user atau dokumen akan dihapus permanen."
            message="Yakin ingin menghapus department ini?"
            confirm-action="delete"
            cancel-action="cancelDelete"
            error-key="delete"
        />
    @endif
</div>
