<div class="space-y-6">
    <x-ui.page-header
        title="Group Akses"
        description="Kelola grup role, akses menu, dan anggota user."
    />

    <x-ui.panel
        title="List Group Akses"
        description="Menampilkan {{ $roles->count() }} data dari total {{ $roles->total() }} group akses."
        :padded="false"
    >
        @if ($canCreate)
            <x-slot name="actions">
                <x-ui.action-button type="button" wire:click="create" class="gap-2">
                    <x-flux.icon name="plus" class="size-4" />
                    Tambah Group
                </x-ui.action-button>
            </x-slot>
        @endif

        <div class="border-b border-slate-200 p-5">
            <x-ui.input
                label="Cari Group"
                name="search"
                wire:model.debounce.300ms="search"
                placeholder="Cari nama group..."
            />
        </div>

        <x-ui.scrollable-table max-height="620px" min-width="860px">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Group</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Akses</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">User</th>
                    <th class="sticky top-0 z-10 bg-slate-50 px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($roles as $role)
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4 font-semibold text-slate-800">{{ $role->nama_role }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $role->permissions_count }} akses</td>
                        <td class="px-5 py-4 text-slate-600">{{ $role->users_count }} user</td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                @if ($canUpdate)
                                    <x-ui.icon-button
                                        icon="pencil"
                                        label="Edit group akses"
                                        size="sm"
                                        wire:click="edit({{ $role->id }})"
                                    />
                                @endif

                                @if ($canDelete)
                                    <x-ui.icon-button
                                        icon="trash"
                                        label="Hapus group akses"
                                        size="sm"
                                        wire:click="confirmDelete({{ $role->id }})"
                                    />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                            Tidak ada group akses yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.scrollable-table>

        <div class="border-t border-slate-100 px-5 py-4">
            {{ $roles->links() }}
        </div>
    </x-ui.panel>

    @if ($showForm)
        <x-ui.modal
            :title="$editingId ? 'Edit Group Akses' : 'Tambah Group Akses'"
            description="Lengkapi detail group, anggota user, dan checklist akses yang dimiliki."
            close-action="cancel"
            max-width="7xl"
            :clip="true"
        >
            <form wire:submit.prevent="save" class="flex min-h-0 flex-1 flex-col">
                <div class="grid min-h-0 flex-1 divide-y divide-slate-200 overflow-hidden lg:grid-cols-[minmax(360px,0.9fr)_minmax(0,1.4fr)] lg:divide-x lg:divide-y-0">
                    <section class="min-h-0 overflow-y-auto px-8 py-6">
                        <div class="space-y-6">
                            <x-ui.form-input
                                label="Nama Group"
                                name="nama_role"
                                wire:model="nama_role"
                                placeholder="Contoh: Admin Kontrol Dokumen"
                            />

                            <div>
                                <span class="mb-2 block text-base font-medium text-slate-500">User</span>
                                <x-ui.user-search-select
                                    name="access_group_user_picker"
                                    :users="$users"
                                    placeholder="Cari dan pilih user"
                                    wire:model="selectedUserId"
                                    wire:change="addUserFromPicker($event.target.value)"
                                    data-clear-on-select="true"
                                    data-access-group-user-picker
                                />
                            </div>

                            <div class="rounded-lg border border-slate-200">
                                <div class="border-b border-slate-100 px-4 py-3">
                                    <h3 class="text-sm font-semibold text-slate-800">User Terpilih</h3>
                                </div>

                                <div class="max-h-[44vh] space-y-2 overflow-y-auto p-4">
                                    @forelse ($selectedUsers as $user)
                                        <div class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-800">{{ $user->name }}</p>
                                                <p class="truncate text-xs text-slate-500">{{ $user->jabatan ?: $user->email }}</p>
                                            </div>

                                            <x-ui.icon-button
                                                icon="x-mark"
                                                label="Hapus user"
                                                variant="ghost"
                                                size="sm"
                                                wire:click="removeUser({{ $user->id }})"
                                            />
                                        </div>
                                    @empty
                                        <p class="py-6 text-center text-sm text-slate-500">Belum ada user yang dipilih.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="min-h-0 overflow-y-auto bg-slate-50/70 px-8 py-6">
                        <x-administration.permission-tree
                            :permission-bundles="$permissionBundles"
                            :permission-ids="$permissionIds"
                        />
                    </section>
                </div>

                <x-ui.modal-step-footer
                    secondary-action="cancel"
                    primary-type="submit"
                />
            </form>
        </x-ui.modal>
    @endif

    @if ($showDeleteModal)
        <x-ui.confirm-modal
            title="Hapus Group Akses"
            description="Group yang masih dipakai approval tidak bisa dihapus."
            message="Yakin ingin menghapus group akses ini?"
            confirm-action="delete"
            cancel-action="cancelDelete"
            error-key="delete"
        />
    @endif
</div>
